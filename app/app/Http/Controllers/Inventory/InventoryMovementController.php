<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventoryIssue;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryIssueItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\StockMovement;
use App\Models\WarehouseLot;
use App\Support\RegionalFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryMovementController extends Controller
{
    public function kardex(Request $request): View
    {
        [$filters, $warehouseQuery, $productsQuery, $query, $scope] = $this->kardexReport($request);
        $movements = (clone $query)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();
        $this->addPageRunningBalances($movements, $scope);

        return view('inventory.kardex', [
            'warehouses' => $warehouseQuery->get(),
            'products' => $productsQuery->get(),
            'filters' => $filters,
            'movements' => $movements,
        ]);
    }

    public function exportKardex(Request $request): StreamedResponse|Response
    {
        [$filters, , , $query, $scope] = $this->kardexReport($request);
        $regional = RegionalFormatter::fromCompany($this->company());
        $format = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'csv', 'pdf'])],
        ])['format'];
        $filename = 'kardex-'.now()->format('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($query, $scope, $filters, $regional) {
                $output = fopen('php://output', 'w');
                if ($output === false) {
                    throw new \RuntimeException('Unable to open the CSV output stream.');
                }
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $this->kardexHeadings());
                foreach ($this->kardexRows($query, $scope, $filters, $regional) as $row) {
                    fputcsv($output, array_map(
                        fn ($value, $index) => in_array($index, [8, 9, 10], true)
                            ? $regional->number($value, $index === 10 ? 4 : 3)
                            : (! in_array($index, [8, 9, 10], true)
                            && preg_match('/^[\t\r=+\-@]/u', (string) $value)
                            ? "'".$value
                            : $value),
                        $row,
                        array_keys($row),
                    ));
                }
                fclose($output);
            }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $rows = iterator_to_array($this->kardexRows($query, $scope, $filters, $regional), false);
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('inventory.kardex-pdf', [
                'headings' => $this->kardexHeadings(),
                'rows' => $rows,
                'filters' => $filters,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kardex');
        $sheet->fromArray($this->kardexHeadings(), null, 'A1');
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '166534']],
        ]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:M1');
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            foreach ($row as $columnIndex => $value) {
                $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $cell = $sheet->getCell("{$column}{$rowNumber}");
                if (in_array($columnIndex, [8, 9, 10], true)) {
                    $cell->setValueExplicit((float) $value, DataType::TYPE_NUMERIC);
                } else {
                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            }
        }
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $writer = new Xlsx($spreadsheet);
        $lastRow = count($rows) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("I2:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("K2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
        }

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function kardexReport(Request $request): array
    {
        $companyId = $this->company()->id;
        $warehouseQuery = $request->user()->accessibleWarehouses()
            ->whereHas('branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
            ->with('branch')
            ->orderBy('name');
        $warehouseIds = (clone $warehouseQuery)->pluck('warehouses.id')->all();
        $scope = StockMovement::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId));
        $productIds = (clone $scope)->select('product_id')->distinct()->pluck('product_id')->all();
        $filters = $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::in($warehouseIds)],
            'product_id' => ['nullable', 'integer', Rule::in($productIds)],
            'from_date' => ['nullable', 'date', 'before_or_equal:today'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date', 'before_or_equal:today'],
        ]);

        if (! empty($filters['warehouse_id'])) {
            $scope->where('warehouse_id', $filters['warehouse_id']);
        }
        if (! empty($filters['product_id'])) {
            $scope->where('product_id', $filters['product_id']);
        }

        $query = (clone $scope)
            ->with(['warehouse.branch', 'product', 'lot.baseCurrency', 'user', 'receipt', 'inventoryIssue'])
            ->when(isset($filters['from_date']), fn (Builder $movements) => $movements->whereDate('occurred_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn (Builder $movements) => $movements->whereDate('occurred_at', '<=', $filters['to_date']));
        $productsQuery = Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('code');

        return [$filters, $warehouseQuery, $productsQuery, $query, $scope];
    }

    private function addPageRunningBalances($movements, Builder $scope): void
    {
        $firstMovement = $movements->first();
        if (! $firstMovement) {
            return;
        }

        $openingBalances = (clone $scope)
            ->where(fn (Builder $before) => $before
                ->where('occurred_at', '<', $firstMovement->occurred_at)
                ->orWhere(fn (Builder $sameTime) => $sameTime
                    ->where('occurred_at', $firstMovement->occurred_at)
                    ->where('id', '<', $firstMovement->id)))
            ->select('warehouse_id', 'product_id')
            ->selectRaw(
                'SUM(CASE WHEN movement_type = ? THEN quantity ELSE -quantity END) as balance',
                [StockMovement::TYPE_RECEIPT],
            )
            ->groupBy('warehouse_id', 'product_id')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->warehouse_id}:{$row->product_id}" => (float) $row->balance])
            ->all();

        foreach ($movements as $movement) {
            $key = "{$movement->warehouse_id}:{$movement->product_id}";
            $quantity = (float) $movement->quantity;
            $openingBalances[$key] = ($openingBalances[$key] ?? 0)
                + ($movement->movement_type === StockMovement::TYPE_RECEIPT ? $quantity : -$quantity);
            $movement->balance_after = $openingBalances[$key];
        }
    }

    private function kardexHeadings(): array
    {
        return [
            __('Sede'),
            __('Bodega'),
            __('Código de producto'),
            __('Producto'),
            __('Fecha'),
            __('Movimiento'),
            __('Documento'),
            __('Lote'),
            __('Cantidad'),
            __('Saldo'),
            __('Costo unitario base'),
            __('Moneda base'),
            __('Registrado por'),
        ];
    }

    private function kardexRows(Builder $query, Builder $scope, array $filters, RegionalFormatter $regional): \Generator
    {
        $balances = [];
        if (isset($filters['from_date'])) {
            $balances = (clone $scope)
                ->where('occurred_at', '<', Carbon::parse($filters['from_date'])->startOfDay())
                ->select('warehouse_id', 'product_id')
                ->selectRaw(
                    'SUM(CASE WHEN movement_type = ? THEN quantity ELSE -quantity END) as balance',
                    [StockMovement::TYPE_RECEIPT],
                )
                ->groupBy('warehouse_id', 'product_id')
                ->get()
                ->mapWithKeys(fn ($row) => ["{$row->warehouse_id}:{$row->product_id}" => (float) $row->balance])
                ->all();
        }

        foreach ((clone $query)->orderBy('occurred_at')->orderBy('id')->lazy(500) as $movement) {
            $key = "{$movement->warehouse_id}:{$movement->product_id}";
            $quantity = (float) $movement->quantity;
            $signedQuantity = $movement->movement_type === StockMovement::TYPE_RECEIPT ? $quantity : -$quantity;
            $balances[$key] = ($balances[$key] ?? 0) + $signedQuantity;
            $documentReference = $movement->movement_type === StockMovement::TYPE_RECEIPT
                ? $movement->receipt?->reference
                : $movement->inventoryIssue?->reference;

            yield [
                $movement->warehouse->branch->name,
                $movement->warehouse->name,
                $movement->product->code,
                $movement->product->name,
                $regional->dateTime($movement->occurred_at),
                $movement->movement_type === StockMovement::TYPE_RECEIPT ? __('Entrada') : __('Salida'),
                $documentReference ?: $movement->reference_type.' #'.$movement->reference_id,
                $movement->lot->lot_code,
                $signedQuantity,
                $balances[$key],
                (float) $movement->unit_cost_base,
                $movement->lot->baseCurrency->code,
                $movement->user->name.' '.$movement->user->last_name,
            ];
        }
    }

    public function receipts(Request $request): View
    {
        $user = $request->user();
        $query = Receipt::query()
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $this->company()->id))
            ->with(['purchaseOrder', 'warehouse.branch', 'receiver', 'items.product'])
            ->latest();
        $this->scopeWarehouses($query, $user);
        if (! $user->hasPermission('inventory.receipts.view')) {
            $query->where('received_by_user_id', $user->id);
        }

        return view('inventory.receipts.index', ['receipts' => $query->paginate(20)]);
    }

    public function showReceipt(Request $request, Receipt $receipt): View
    {
        $receipt->load(['purchaseOrder.quoteCurrency', 'purchaseOrder.baseCurrency', 'warehouse.branch', 'receiver', 'items.product', 'items.lot']);
        abort_unless(
            $receipt->warehouse->branch->company_id === $this->company()->id
                && $request->user()->canAccessWarehouse($receipt->warehouse_id),
            403,
        );
        abort_unless(
            $request->user()->hasPermission('inventory.receipts.view')
                || $receipt->received_by_user_id === $request->user()->id,
            403,
        );

        return view('inventory.receipts.show', compact('receipt'));
    }

    public function createReceipt(Request $request): View
    {
        $order = $this->accessibleOrder((int) $request->query('purchase_order_id'), $request)
            ->load(['items.product', 'receipts.items', 'requisition.warehouse.branch']);
        abort_unless($order->status === PurchaseOrder::STATUS_APPROVED, 403);

        $items = $order->items->map(function (PurchaseOrderItem $item) {
            $receivedMilli = $item->receiptItems->sum(fn (ReceiptItem $receiptItem) => $this->quantityMilli($receiptItem->quantity));
            $item->received_milli = $receivedMilli;
            $item->remaining_milli = max(0, $this->quantityMilli($item->quantity) - $receivedMilli);

            return $item;
        })->filter(fn (PurchaseOrderItem $item) => $item->remaining_milli > 0)->values();
        abort_unless($items->isNotEmpty(), 409, __('Esta orden ya fue recibida por completo.'));

        return view('inventory.receipts.create', [
            'purchaseOrder' => $order,
            'items' => $items,
        ]);
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'document_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'items.*.expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $companyId = $this->company()->id;
        $user = $request->user();

        $receipt = DB::transaction(function () use ($validated, $request, $companyId, $user) {
            $order = PurchaseOrder::query()
                ->whereKey($validated['purchase_order_id'])
                ->where('status', PurchaseOrder::STATUS_APPROVED)
                ->whereHas('requisition.department', fn (Builder $departments) => $departments->where('company_id', $companyId))
                ->with(['items.product', 'requisition'])
                ->lockForUpdate()
                ->firstOrFail();
            $warehouseId = $order->requisition->warehouse_id;
            abort_unless($warehouseId !== null && $user->canAccessWarehouse($warehouseId), 403);

            $orderItems = $order->items->keyBy('id');
            $requestedItems = collect($validated['items'])->mapWithKeys(
                fn (array $item, int $index) => [(int) $item['purchase_order_item_id'] => [...$item, '_form_index' => $index]],
            );
            if ($requestedItems->keys()->diff($orderItems->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => __('Incluye únicamente productos pertenecientes a esta orden de compra.')]);
            }

            foreach ($requestedItems as $orderItemId => $receivedData) {
                /** @var PurchaseOrderItem $orderItem */
                $orderItem = PurchaseOrderItem::query()->whereKey($orderItemId)->lockForUpdate()->firstOrFail();
                $receivedMilli = $orderItem->receiptItems()->sum('quantity');
                $remainingMilli = $this->quantityMilli($orderItem->quantity) - $this->quantityMilli($receivedMilli);
                $quantityMilli = $this->quantityMilli($receivedData['quantity']);

                if ($quantityMilli > $remainingMilli) {
                    throw ValidationException::withMessages([
                        'items' => __('La cantidad recibida no puede superar el saldo pendiente de la orden.'),
                    ]);
                }

                $product = $orderItems[$orderItemId]->product;
                $expiresOn = $receivedData['expires_on'] ?? null;
                if ($product->tracks_expiration && ! $expiresOn) {
                    throw ValidationException::withMessages([
                        "items.{$receivedData['_form_index']}.expires_on" => __('Este producto requiere fecha de vencimiento.'),
                    ]);
                }
                if (! $product->tracks_expiration && $expiresOn) {
                    throw ValidationException::withMessages([
                        'items' => __('No registres vencimiento para un producto que no controla fecha de expiración.'),
                    ]);
                }
            }

            $receivedAt = now();
            $receipt = Receipt::create([
                'reference' => 'TMP-'.Str::random(24),
                'purchase_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'received_by_user_id' => $user->id,
                'document_reference' => $validated['document_reference'] ?? null,
                'received_at' => $receivedAt,
            ]);
            $receipt->update(['reference' => 'ENT-'.str_pad((string) $receipt->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($requestedItems as $orderItemId => $receivedData) {
                $orderItem = $orderItems[$orderItemId];
                $receiptItem = ReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $receivedData['quantity'],
                    'unit_cost' => $orderItem->unit_cost,
                    'unit_cost_base' => $orderItem->unit_cost_base,
                    'expires_on' => $receivedData['expires_on'] ?? null,
                ]);
                $lot = WarehouseLot::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $orderItem->product_id,
                    'receipt_item_id' => $receiptItem->id,
                    'lot_code' => $receipt->reference.'-'.str_pad((string) $receiptItem->id, 4, '0', STR_PAD_LEFT),
                    'quantity_received' => $receivedData['quantity'],
                    'unit_cost' => $orderItem->unit_cost,
                    'unit_cost_base' => $orderItem->unit_cost_base,
                    'quote_currency_id' => $order->quote_currency_id,
                    'base_currency_id' => $order->base_currency_id,
                    'exchange_rate' => $order->exchange_rate,
                    'expires_on' => $receivedData['expires_on'] ?? null,
                    'received_at' => $receivedAt,
                ]);
                StockMovement::create([
                    'warehouse_lot_id' => $lot->id,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $orderItem->product_id,
                    'user_id' => $user->id,
                    'movement_type' => StockMovement::TYPE_RECEIPT,
                    'reference_type' => 'receipt',
                    'reference_id' => $receipt->id,
                    'quantity' => $receivedData['quantity'],
                    'unit_cost_base' => $orderItem->unit_cost_base,
                    'base_currency_id' => $order->base_currency_id,
                    'occurred_at' => $receivedAt,
                ]);
            }

            $this->audit($request, $companyId, 'received', 'receipt', $receipt->id, [
                'purchase_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'items' => $receipt->items()->get(['product_id', 'quantity', 'unit_cost', 'unit_cost_base', 'expires_on'])->toArray(),
            ]);

            return $receipt;
        });

        return redirect()->route('inventory.receipts.index')->with('status', __('Entrada registrada correctamente.'));
    }

    public function issues(Request $request): View
    {
        $query = InventoryIssue::query()
            ->whereHas('requisition.department', fn (Builder $departments) => $departments->where('company_id', $this->company()->id))
            ->with(['requisition', 'warehouse.branch', 'issuer', 'items'])
            ->latest();
        $this->scopeWarehouses($query, $request->user());
        if (! $request->user()->hasPermission('inventory.issues.view')) {
            $query->where('issued_by_user_id', $request->user()->id);
        }

        return view('inventory.issues.index', ['issues' => $query->paginate(20)]);
    }

    public function showIssue(Request $request, InventoryIssue $issue): View
    {
        $issue->load(['requisition', 'warehouse.branch', 'issuer', 'items.allocations.lot.quoteCurrency', 'items.allocations.lot.baseCurrency']);
        abort_unless(
            $issue->warehouse->branch->company_id === $this->company()->id
                && $request->user()->canAccessWarehouse($issue->warehouse_id),
            403,
        );
        abort_unless(
            $request->user()->hasPermission('inventory.issues.view')
                || $issue->issued_by_user_id === $request->user()->id,
            403,
        );

        return view('inventory.issues.show', compact('issue'));
    }

    public function createIssue(Request $request): View
    {
        $requisition = $this->accessibleRequisition((int) $request->query('requisition_id'), $request)
            ->load(['items.product', 'items.inventoryIssueItems', 'warehouse.branch']);
        abort_unless($requisition->status === Requisition::STATUS_APPROVED, 403);

        $items = $requisition->items->map(function (RequisitionItem $item) use ($requisition) {
            $issuedMilli = $item->inventoryIssueItems->sum(fn (InventoryIssueItem $issueItem) => $this->quantityMilli($issueItem->quantity));
            $item->issued_milli = $issuedMilli;
            $item->remaining_milli = max(0, $this->quantityMilli($item->quantity) - $issuedMilli);
            $item->available_milli = $this->availableQuantityMilli($requisition->warehouse_id, $item->product);

            return $item;
        })->filter(fn (RequisitionItem $item) => $item->remaining_milli > 0)->values();
        abort_unless($items->isNotEmpty(), 409, __('Esta requisición ya fue despachada por completo.'));

        return view('inventory.issues.create', compact('requisition', 'items'));
    }

    public function storeIssue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requisition_id' => ['required', 'integer', 'exists:requisitions,id'],
            'document_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.requisition_item_id' => ['required', 'integer', 'distinct', 'exists:requisition_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
        ]);
        $companyId = $this->company()->id;
        $user = $request->user();

        $issue = DB::transaction(function () use ($validated, $request, $companyId, $user) {
            $requisition = Requisition::query()
                ->whereKey($validated['requisition_id'])
                ->where('status', Requisition::STATUS_APPROVED)
                ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $companyId))
                ->with('items.product')
                ->lockForUpdate()
                ->firstOrFail();
            $warehouseId = $requisition->warehouse_id;
            abort_unless($warehouseId !== null && $user->canAccessWarehouse($warehouseId), 403);
            $requisitionItems = $requisition->items->keyBy('id');
            $requestedItems = collect($validated['items'])->mapWithKeys(
                fn (array $item, int $index) => [(int) $item['requisition_item_id'] => [...$item, '_form_index' => $index]],
            );
            if ($requestedItems->keys()->diff($requisitionItems->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => __('Incluye únicamente productos pertenecientes a esta requisición.')]);
            }

            $allocationPlans = [];
            foreach ($requestedItems as $requisitionItemId => $issueData) {
                $item = RequisitionItem::query()->whereKey($requisitionItemId)->lockForUpdate()->firstOrFail();
                $issuedMilli = $item->inventoryIssueItems()->sum('quantity');
                $remainingMilli = $this->quantityMilli($item->quantity) - $this->quantityMilli($issuedMilli);
                $quantityMilli = $this->quantityMilli($issueData['quantity']);
                if ($quantityMilli > $remainingMilli) {
                    throw ValidationException::withMessages([
                        'items' => __('La cantidad despachada no puede superar el saldo aprobado pendiente de la requisición.'),
                    ]);
                }

                $allocationPlans[$requisitionItemId] = $this->planLotAllocations(
                    $warehouseId,
                    $item->product,
                    $quantityMilli,
                    $issueData['_form_index'],
                );
            }

            $issuedAt = now();
            $issue = InventoryIssue::create([
                'reference' => 'TMP-'.Str::random(24),
                'requisition_id' => $requisition->id,
                'warehouse_id' => $warehouseId,
                'issued_by_user_id' => $user->id,
                'document_reference' => $validated['document_reference'] ?? null,
                'issued_at' => $issuedAt,
            ]);
            $issue->update(['reference' => 'SAL-'.str_pad((string) $issue->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($requestedItems as $requisitionItemId => $issueData) {
                $sourceItem = $requisitionItems[$requisitionItemId];
                $issueItem = InventoryIssueItem::create([
                    'inventory_issue_id' => $issue->id,
                    'requisition_item_id' => $sourceItem->id,
                    'product_id' => $sourceItem->product_id,
                    'product_code' => $sourceItem->product_code,
                    'product_name' => $sourceItem->product_name,
                    'unit_abbreviation' => $sourceItem->unit_abbreviation,
                    'quantity' => $issueData['quantity'],
                ]);

                foreach ($allocationPlans[$requisitionItemId] as $allocation) {
                    InventoryIssueAllocation::create([
                        'inventory_issue_item_id' => $issueItem->id,
                        'warehouse_lot_id' => $allocation['lot']->id,
                        'quantity' => $this->quantityString($allocation['quantity_milli']),
                        'unit_cost' => $allocation['lot']->unit_cost,
                        'unit_cost_base' => $allocation['lot']->unit_cost_base,
                    ]);
                    StockMovement::create([
                        'warehouse_lot_id' => $allocation['lot']->id,
                        'warehouse_id' => $warehouseId,
                        'product_id' => $sourceItem->product_id,
                        'user_id' => $user->id,
                        'movement_type' => StockMovement::TYPE_ISSUE,
                        'reference_type' => 'inventory_issue',
                        'reference_id' => $issue->id,
                        'quantity' => $this->quantityString($allocation['quantity_milli']),
                        'unit_cost_base' => $allocation['lot']->unit_cost_base,
                        'base_currency_id' => $allocation['lot']->base_currency_id,
                        'occurred_at' => $issuedAt,
                    ]);
                }
            }

            $this->audit($request, $companyId, 'issued', 'inventory_issue', $issue->id, [
                'requisition_id' => $requisition->id,
                'warehouse_id' => $warehouseId,
                'items' => $issue->items()->get(['product_id', 'quantity'])->toArray(),
            ]);

            return $issue;
        });

        return redirect()->route('inventory.issues.index')->with('status', __('Salida registrada correctamente.'));
    }

    private function accessibleOrder(int $id, Request $request): PurchaseOrder
    {
        $order = PurchaseOrder::query()
            ->whereKey($id)
            ->whereHas('requisition.department', fn (Builder $departments) => $departments->where('company_id', $this->company()->id))
            ->with(['requisition.warehouse.branch'])
            ->firstOrFail();
        abort_unless(
            $order->requisition->warehouse_id !== null
                && $request->user()->canAccessWarehouse($order->requisition->warehouse_id),
            403,
        );

        return $order;
    }

    private function accessibleRequisition(int $id, Request $request): Requisition
    {
        $requisition = Requisition::query()
            ->whereKey($id)
            ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $this->company()->id))
            ->with('warehouse.branch')
            ->firstOrFail();
        abort_unless(
            $requisition->warehouse_id !== null
                && $request->user()->canAccessWarehouse($requisition->warehouse_id),
            403,
        );

        return $requisition;
    }

    private function scopeWarehouses(Builder $query, $user): void
    {
        if (! $user->hasGlobalWarehouseAccess()) {
            $query->whereIn('warehouse_id', $user->accessibleWarehouses()->select('warehouses.id'));
        }
    }

    private function planLotAllocations(int $warehouseId, Product $product, int $quantityMilli, int|string $itemIndex): array
    {
        $lots = WarehouseLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $product->id)
            ->when(
                $product->tracks_expiration,
                fn (Builder $query) => $query->whereNotNull('expires_on')->whereDate('expires_on', '>=', today()),
                fn (Builder $query) => $query->whereNull('expires_on'),
            )
            ->when($product->tracks_expiration, fn (Builder $query) => $query->orderBy('expires_on'))
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        $remainingMilli = $quantityMilli;
        foreach ($lots as $lot) {
            $movements = $lot->movements()->lockForUpdate()->get(['movement_type', 'quantity']);
            $balanceMilli = $movements->sum(fn (StockMovement $movement) => $movement->movement_type === StockMovement::TYPE_RECEIPT
                ? $this->quantityMilli($movement->quantity)
                : -$this->quantityMilli($movement->quantity));
            if ($balanceMilli <= 0) {
                continue;
            }

            $allocatedMilli = min($remainingMilli, $balanceMilli);
            $allocations[] = ['lot' => $lot, 'quantity_milli' => $allocatedMilli];
            $remainingMilli -= $allocatedMilli;
            if ($remainingMilli === 0) {
                break;
            }
        }

        if ($remainingMilli > 0) {
            throw ValidationException::withMessages([
                "items.{$itemIndex}.quantity" => $product->tracks_expiration
                    ? __('No hay suficiente existencia vigente disponible para este producto en la bodega.')
                    : __('No hay suficiente existencia disponible para este producto en la bodega.'),
            ]);
        }

        return $allocations;
    }

    private function availableQuantityMilli(int $warehouseId, Product $product): int
    {
        $lots = WarehouseLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $product->id)
            ->when(
                $product->tracks_expiration,
                fn (Builder $query) => $query->whereNotNull('expires_on')->whereDate('expires_on', '>=', today()),
                fn (Builder $query) => $query->whereNull('expires_on'),
            )
            ->with('movements')
            ->get();

        return $lots->sum(fn (WarehouseLot $lot) => $lot->movements->sum(
            fn (StockMovement $movement) => $movement->movement_type === StockMovement::TYPE_RECEIPT
                ? $this->quantityMilli($movement->quantity)
                : -$this->quantityMilli($movement->quantity),
        ));
    }

    private function quantityMilli(mixed $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function quantityString(int $quantityMilli): string
    {
        return number_format($quantityMilli / 1000, 3, '.', '');
    }

    private function company(): Company
    {
        return Company::query()->where('singleton_key', 'primary')->firstOrFail();
    }

    private function audit(Request $request, int $companyId, string $action, string $entityType, int $entityId, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'company_id' => $companyId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }
}
