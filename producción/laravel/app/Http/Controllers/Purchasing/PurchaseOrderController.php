<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Support\DecimalMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $this->company()->id;
        $user = $request->user();
        $query = PurchaseOrder::query()
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
            ->with(['supplier', 'quoteCurrency', 'baseCurrency', 'requisition', 'warehouse.branch'])
            ->latest();

        if (! $user->hasGlobalWarehouseAccess()) {
            $query->whereIn('warehouse_id', $user->accessibleWarehouses()->select('warehouses.id'));
        }

        if (! $user->hasPermission('purchases.orders.view')
            && ! $user->hasPermission('purchases.quotes.generate_orders')
            && ! $user->hasPermission('purchases.orders.submit')
            && ! $user->hasPermission('purchases.orders.approve')
            && ! $user->hasPermission('purchases.orders.approve_any')
            && ! $user->hasPermission('purchases.orders.reject')
            && ! $user->hasPermission('purchases.orders.create_direct')) {
            $query->where('created_by_user_id', $user->id);
        }

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_PENDING_APPROVAL,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_REJECTED,
            ])],
        ]);
        $pendingCount = (clone $query)
            ->where('status', PurchaseOrder::STATUS_PENDING_APPROVAL)
            ->count();
        $query->when(
            isset($filters['status']),
            fn (Builder $orders) => $orders->where('status', $filters['status']),
        );

        return view('purchasing.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'statusFilter' => $filters['status'] ?? '',
            'pendingCount' => $pendingCount,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $requisitionId = $request->query('requisition_id');
        abort_unless(is_numeric($requisitionId) && (int) $requisitionId > 0, 404);

        return redirect()->route('purchasing.quotes.index', ['requisition' => (int) $requisitionId]);
    }

    public function createDirect(Request $request): View
    {
        $company = $this->company();
        $mode = $request->query('mode');
        abort_if($mode !== null && ! in_array($mode, ['requisition', 'standalone'], true), 404);

        if ($mode === null) {
            return view('purchasing.orders.direct-source');
        }

        $baseCurrency = $company->baseCurrency()->firstOrFail();
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('legal_name')->get();
        $currencies = Currency::query()->where('is_active', true)->orderBy('code')->get();

        if ($mode === 'requisition') {
            $requisitions = Requisition::query()
                ->where('status', Requisition::STATUS_APPROVED)
                ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $company->id))
                ->when(
                    ! $request->user()->hasGlobalWarehouseAccess(),
                    fn (Builder $query) => $query->whereIn(
                        'warehouse_id',
                        $request->user()->accessibleWarehouses()->select('warehouses.id'),
                    ),
                )
                ->whereHas('items', fn (Builder $items) => $items
                    ->whereNotExists(fn ($orders) => $orders->selectRaw('1')
                        ->from('purchase_order_items')
                        ->whereColumn('purchase_order_items.requisition_item_id', 'requisition_items.id'))
                    ->whereRaw('requisition_items.quantity > COALESCE((SELECT SUM(inventory_issue_items.quantity) FROM inventory_issue_items WHERE inventory_issue_items.requisition_item_id = requisition_items.id), 0)'))
                ->with(['department', 'warehouse.branch'])
                ->latest()
                ->paginate(20);
            $requisition = null;
            $items = collect();

            if ($request->filled('requisition_id')) {
                $requisition = $this->accessibleDirectRequisition((int) $request->query('requisition_id'), $request, $company->id);
                $items = $this->directRequisitionItems($requisition);
                abort_unless($items->isNotEmpty(), 409, __('Esta requisición no tiene productos pendientes de compra para una orden directa.'));
            }

            return view('purchasing.orders.direct-create', [
                'mode' => $mode,
                'requisitions' => $requisitions,
                'requisition' => $requisition,
                'items' => $items,
                'warehouses' => collect(),
                'products' => collect(),
                'suppliers' => $suppliers,
                'currencies' => $currencies,
                'baseCurrency' => $baseCurrency,
            ]);
        }

        return view('purchasing.orders.direct-create', [
            'mode' => $mode,
            'requisitions' => collect(),
            'requisition' => null,
            'items' => collect(),
            'warehouses' => $request->user()->accessibleWarehouses()
                ->where('is_active', true)
                ->whereHas('branch', fn (Builder $branches) => $branches->where('company_id', $company->id))
                ->with('branch')
                ->orderBy('name')
                ->get(),
            'products' => Product::query()->where('is_active', true)->with('unit')->orderBy('code')->get(),
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency,
        ]);
    }

    public function storeDirect(Request $request): RedirectResponse
    {
        $mode = $request->input('mode');
        abort_unless(in_array($mode, ['requisition', 'standalone'], true), 404);
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['requisition', 'standalone'])],
            'requisition_id' => [Rule::requiredIf($mode === 'requisition'), 'nullable', 'integer', 'exists:requisitions,id'],
            'warehouse_id' => [Rule::requiredIf($mode === 'standalone'), 'nullable', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'quote_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['required', 'numeric', 'decimal:0,8', 'min:0.00000001', 'max:999999999.99999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [Rule::requiredIf($mode === 'standalone'), 'nullable', 'integer', 'distinct', 'exists:products,id'],
            'items.*.requisition_item_id' => [Rule::requiredIf($mode === 'requisition'), 'nullable', 'integer', 'distinct', 'exists:requisition_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999.9999'],
            'items.*.tax_rate' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:100'],
            'items.*.brand' => ['nullable', 'string', 'max:100'],
            'items.*.delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $company = $this->company();
        $user = $request->user();

        $order = DB::transaction(function () use ($request, $validated, $mode, $company, $user) {
            $currency = Currency::query()->whereKey($validated['quote_currency_id'])->where('is_active', true)->firstOrFail();
            if ($currency->id === $company->base_currency_id
                && DecimalMath::compare((string) $validated['exchange_rate'], '1', 8) !== 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => __('La tasa debe ser 1 cuando la moneda cotizada coincide con la moneda base.'),
                ]);
            }

            $requisition = null;
            if ($mode === 'requisition') {
                $requisition = $this->accessibleDirectRequisition((int) $validated['requisition_id'], $request, $company->id, true);
                $warehouseId = $requisition->warehouse_id;
                $requestedItems = collect($validated['items']);
                $requisitionItems = RequisitionItem::query()
                    ->where('requisition_id', $requisition->id)
                    ->whereIn('id', $requestedItems->pluck('requisition_item_id'))
                    ->whereNotExists(fn ($orders) => $orders->selectRaw('1')
                        ->from('purchase_order_items')
                        ->whereColumn('purchase_order_items.requisition_item_id', 'requisition_items.id'))
                    ->with('product')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($requisitionItems->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' => __('Selecciona únicamente productos pendientes de compra de esta requisición.'),
                    ]);
                }
                foreach ($requestedItems as $index => $line) {
                    $requisitionItem = $requisitionItems[(int) $line['requisition_item_id']];
                    $issued = $requisitionItem->inventoryIssueItems()->sum('quantity');
                    $remainingMilli = max(0, $this->quantityMilli((string) $requisitionItem->quantity) - $this->quantityMilli((string) $issued));
                    if ($this->quantityMilli((string) $line['quantity']) !== $remainingMilli) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => __('La cantidad debe coincidir con el saldo pendiente de compra de la requisición.'),
                        ]);
                    }
                }
            } else {
                $warehouse = $user->accessibleWarehouses()
                    ->where('is_active', true)
                    ->whereHas('branch', fn (Builder $branches) => $branches->where('company_id', $company->id))
                    ->whereKey($validated['warehouse_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $warehouseId = $warehouse->id;
                $requestedItems = collect($validated['items']);
                $products = Product::query()
                    ->whereIn('id', $requestedItems->pluck('product_id'))
                    ->where('is_active', true)
                    ->with('unit')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($products->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' => __('Selecciona únicamente productos activos del catálogo.'),
                    ]);
                }
            }

            $order = PurchaseOrder::query()->create([
                'reference' => 'TMP-'.\Illuminate\Support\Str::random(24),
                'requisition_id' => $requisition?->id,
                'warehouse_id' => $warehouseId,
                'supplier_quote_id' => null,
                'supplier_id' => $validated['supplier_id'],
                'quote_currency_id' => $currency->id,
                'base_currency_id' => $company->base_currency_id,
                'exchange_rate' => $validated['exchange_rate'],
                'created_by_user_id' => $user->id,
                'quote_reference' => null,
                'quote_date' => null,
                'status' => PurchaseOrder::STATUS_DRAFT,
            ]);
            $order->update(['reference' => 'OC-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($requestedItems as $line) {
                $sourceItem = $mode === 'requisition'
                    ? $requisitionItems[(int) $line['requisition_item_id']]
                    : $products[(int) $line['product_id']];
                $baseUnitCost = DecimalMath::multiply(
                    (string) $line['unit_cost'],
                    4,
                    (string) $validated['exchange_rate'],
                    8,
                    4,
                );
                if (DecimalMath::compare($baseUnitCost, '999999999999999999.9999', 4) > 0) {
                    throw ValidationException::withMessages([
                        'items' => __('El costo convertido supera el máximo permitido.'),
                    ]);
                }
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $order->id,
                    'requisition_item_id' => $mode === 'requisition' ? $sourceItem->id : null,
                    'supplier_quote_item_id' => null,
                    'product_id' => $sourceItem->product_id ?? $sourceItem->id,
                    'product_code' => $sourceItem->product_code ?? $sourceItem->code,
                    'product_name' => $sourceItem->product_name ?? $sourceItem->name,
                    'unit_abbreviation' => $sourceItem->unit_abbreviation ?? $sourceItem->unit->abbreviation,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'unit_cost_base' => $baseUnitCost,
                    'tax_rate' => $line['tax_rate'],
                    'brand' => $line['brand'] ?? null,
                    'delivery_days' => $line['delivery_days'] ?? null,
                ]);
            }

            $this->audit($request, $company->id, 'created_direct', $order, [
                'origin' => $mode,
                'requisition_id' => $requisition?->id,
                'warehouse_id' => $warehouseId,
                'supplier_id' => $order->supplier_id,
                'items' => $order->items()->get(['product_id', 'quantity', 'unit_cost'])->toArray(),
            ]);

            return $order;
        });

        return redirect()->route('purchasing.orders.show', $order)
            ->with('status', __('Orden directa creada como borrador. Envíala a aprobación antes de recibir productos.'));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'quoteCurrency',
            'baseCurrency',
            'creator',
            'approver',
            'rejector',
            'requisition.department',
            'warehouse.branch',
            'items.product',
            'items.requisitionItem',
            'items.receiptItems',
            'receipts.items',
        ]);
        $this->authorizeOrderAccess($request, $purchaseOrder);

        return view('purchasing.orders.show', compact('purchaseOrder'));
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'quoteCurrency',
            'baseCurrency',
            'requisition',
            'warehouse.branch',
            'items.receiptItems',
        ]);
        $this->authorizeOrderAccess($request, $purchaseOrder);
        abort_unless(
            $purchaseOrder->status === PurchaseOrder::STATUS_REJECTED
                && $purchaseOrder->created_by_user_id === $request->user()->id
                && $request->user()->hasPermission('purchases.orders.submit'),
            403,
        );

        return view('purchasing.orders.edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('legal_name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'baseCurrency' => $this->company()->baseCurrency()->firstOrFail(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'quote_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['required', 'numeric', 'decimal:0,8', 'min:0.00000001', 'max:999999999.99999999'],
            'quote_reference' => [$purchaseOrder->supplier_quote_id ? 'required' : 'nullable', 'string', 'max:100'],
            'quote_date' => [$purchaseOrder->supplier_quote_id ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999.9999'],
            'items.*.tax_rate' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:100'],
            'items.*.brand' => ['nullable', 'string', 'max:100'],
            'items.*.delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $company = $this->company();
        $user = $request->user();

        DB::transaction(function () use ($request, $purchaseOrder, $validated, $company, $user) {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $company->id))
                ->with(['warehouse.branch', 'items.requisitionItem.inventoryIssueItems', 'items.receiptItems'])
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorizeOrderAccess($request, $order);
            abort_unless(
                $order->status === PurchaseOrder::STATUS_REJECTED
                    && $order->created_by_user_id === $user->id
                    && $user->hasPermission('purchases.orders.submit'),
                403,
            );

            $existingItemIds = $order->items->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();
            $submittedItemIds = collect(array_keys($validated['items']))->map(fn ($id) => (string) $id)->sort()->values();
            if ($existingItemIds->all() !== $submittedItemIds->all()) {
                throw ValidationException::withMessages([
                    'items' => __('No se pueden agregar ni retirar productos de una orden devuelta.'),
                ]);
            }

            $currency = Currency::query()->whereKey($validated['quote_currency_id'])->where('is_active', true)->firstOrFail();
            if ($currency->id === $company->base_currency_id
                && DecimalMath::compare((string) $validated['exchange_rate'], '1', 8) !== 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => __('La tasa debe ser 1 cuando la moneda cotizada coincide con la moneda base.'),
                ]);
            }

            $before = $this->snapshot($order);
            foreach ($order->items as $item) {
                $itemData = $validated['items'][$item->id];
                $receivedMilli = $item->receiptItems->sum(fn ($receipt) => $this->quantityMilli((string) $receipt->quantity));
                $issuedMilli = $item->requisitionItem?->inventoryIssueItems
                    ->sum(fn ($issue) => $this->quantityMilli((string) $issue->quantity)) ?? 0;
                $availableMilli = $item->requisitionItem
                    ? max(0, $this->quantityMilli((string) $item->requisitionItem->quantity) - $issuedMilli)
                    : PHP_INT_MAX;
                $quantityMilli = $this->quantityMilli((string) $itemData['quantity']);
                if ($quantityMilli < $receivedMilli || $quantityMilli > $availableMilli) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.quantity" => __('La cantidad debe cubrir lo recibido y no superar el saldo pendiente de la requisición.'),
                    ]);
                }

                $baseUnitCost = DecimalMath::multiply(
                    (string) $itemData['unit_cost'],
                    4,
                    (string) $validated['exchange_rate'],
                    8,
                    4,
                );
                if (DecimalMath::compare($baseUnitCost, '999999999999999999.9999', 4) > 0) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.unit_cost" => __('El costo convertido supera el máximo permitido.'),
                    ]);
                }
                $item->update([
                    'quantity' => $itemData['quantity'],
                    'unit_cost' => $itemData['unit_cost'],
                    'unit_cost_base' => $baseUnitCost,
                    'tax_rate' => $itemData['tax_rate'],
                    'brand' => $itemData['brand'] ?? null,
                    'delivery_days' => $itemData['delivery_days'] ?? null,
                ]);
            }

            $order->update([
                'supplier_id' => $validated['supplier_id'],
                'quote_currency_id' => $currency->id,
                'base_currency_id' => $company->base_currency_id,
                'exchange_rate' => $validated['exchange_rate'],
                'quote_reference' => $validated['quote_reference'] ?? null,
                'quote_date' => $validated['quote_date'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
            ]);
            $order->load(['supplier', 'quoteCurrency', 'baseCurrency', 'items']);
            $this->audit($request, $company->id, 'updated_after_rejection', $order, [
                'before' => $before,
                'after' => $this->snapshot($order),
            ]);
        });

        return redirect()->route('purchasing.orders.show', $purchaseOrder)
            ->with('status', __('La orden fue corregida. Envíala nuevamente para aprobación.'));
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('purchases.orders.submit'), 403);
        $companyId = $this->company()->id;

        DB::transaction(function () use ($request, $purchaseOrder, $companyId) {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
                ->with('warehouse')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $request->user()->canAccessWarehouse($order->warehouse_id),
                403,
            );
            abort_unless($order->created_by_user_id === $request->user()->id, 403);
            abort_unless($order->status === PurchaseOrder::STATUS_DRAFT, 409);

            $previousRejection = $order->rejection_reason;
            $order->update([
                'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
                'rejection_reason' => null,
                'rejected_by_user_id' => null,
                'rejected_at' => null,
            ]);
            $this->audit($request, $companyId, 'submitted_for_approval', $order, [
                'before' => [
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'rejection_reason' => $previousRejection,
                ],
                'after' => [
                    'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
                    'rejection_reason' => null,
                ],
            ]);
        });

        return redirect()->route('purchasing.orders.show', $purchaseOrder)
            ->with('status', __('Orden enviada a aprobación.'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $canApproveAny = $request->user()->hasPermission('purchases.orders.approve_any');
        abort_unless(
            $request->user()->hasPermission('purchases.orders.approve') || $canApproveAny,
            403,
        );
        $companyId = $this->company()->id;

        DB::transaction(function () use ($request, $purchaseOrder, $companyId) {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
                ->with('warehouse')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $request->user()->canAccessWarehouse($order->warehouse_id),
                403,
            );
            abort_unless($order->status === PurchaseOrder::STATUS_PENDING_APPROVAL, 409);
            abort_unless(
                $order->created_by_user_id !== $request->user()->id
                    || $request->user()->hasPermission('purchases.orders.approve_any'),
                403,
                __('Quien crea la orden no puede aprobarla.'),
            );

            $order->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $this->audit($request, $companyId, 'approved', $order, [
                'before' => ['status' => PurchaseOrder::STATUS_PENDING_APPROVAL],
                'after' => ['status' => PurchaseOrder::STATUS_APPROVED],
            ]);
        });

        return redirect()->route('purchasing.orders.show', $purchaseOrder)->with('status', __('Orden de compra aprobada; ya puede recibir productos.'));
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('purchases.orders.reject'), 403);
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $companyId = $this->company()->id;

        DB::transaction(function () use ($request, $purchaseOrder, $companyId, $validated) {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
                ->with('warehouse')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $request->user()->canAccessWarehouse($order->warehouse_id),
                403,
            );
            abort_unless($order->status === PurchaseOrder::STATUS_PENDING_APPROVAL, 409);
            abort_unless($order->created_by_user_id !== $request->user()->id, 403, __('Quien crea la orden no puede rechazarla.'));

            $order->update([
                'status' => PurchaseOrder::STATUS_REJECTED,
                'rejection_reason' => $validated['rejection_reason'],
                'rejected_by_user_id' => $request->user()->id,
                'rejected_at' => now(),
            ]);
            $this->audit($request, $companyId, 'rejected', $order, [
                'before' => ['status' => PurchaseOrder::STATUS_PENDING_APPROVAL],
                'after' => [
                    'status' => PurchaseOrder::STATUS_REJECTED,
                    'rejection_reason' => $validated['rejection_reason'],
                ],
            ]);
        });

        return redirect()->route('purchasing.orders.show', $purchaseOrder)
            ->with('status', __('Orden rechazada y devuelta a su creador.'));
    }

    private function accessibleDirectRequisition(int $id, Request $request, int $companyId, bool $lock = false): Requisition
    {
        $query = Requisition::query()
            ->whereKey($id)
            ->where('status', Requisition::STATUS_APPROVED)
            ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $companyId))
            ->when(
                ! $request->user()->hasGlobalWarehouseAccess(),
                fn (Builder $requisitions) => $requisitions->whereIn(
                    'warehouse_id',
                    $request->user()->accessibleWarehouses()->select('warehouses.id'),
                ),
            )
            ->with(['warehouse.branch', 'items.product'])
            ->when($lock, fn (Builder $requisitions) => $requisitions->lockForUpdate());

        return $query->firstOrFail();
    }

    private function directRequisitionItems(Requisition $requisition)
    {
        return $requisition->items()
            ->whereNotExists(fn ($orders) => $orders->selectRaw('1')
                ->from('purchase_order_items')
                ->whereColumn('purchase_order_items.requisition_item_id', 'requisition_items.id'))
            ->whereRaw('requisition_items.quantity > COALESCE((SELECT SUM(inventory_issue_items.quantity) FROM inventory_issue_items WHERE inventory_issue_items.requisition_item_id = requisition_items.id), 0)')
            ->with('product')
            ->orderBy('id')
            ->get()
            ->each(function (RequisitionItem $item) {
                $issued = $item->inventoryIssueItems()->sum('quantity');
                $item->remaining_quantity = number_format(
                    max(0, $this->quantityMilli((string) $item->quantity) - $this->quantityMilli((string) $issued)) / 1000,
                    3,
                    '.',
                    '',
                );
            });
    }

    private function authorizeOrderAccess(Request $request, PurchaseOrder $order): void
    {
        abort_unless(
            $order->warehouse->branch->company_id === $this->company()->id,
            404,
        );
        abort_unless(
            $request->user()->canAccessWarehouse($order->warehouse_id),
            403,
            __('No tienes acceso a la bodega asociada a esta orden.'),
        );
        abort_unless(
            $request->user()->hasPermission('purchases.orders.view')
                || $request->user()->hasPermission('purchases.quotes.generate_orders')
                || $request->user()->hasPermission('purchases.orders.submit')
                || $request->user()->hasPermission('purchases.orders.approve')
                || $request->user()->hasPermission('purchases.orders.approve_any')
                || $request->user()->hasPermission('purchases.orders.reject')
                || $request->user()->hasPermission('purchases.orders.create_direct')
                || $order->created_by_user_id === $request->user()->id
                || $order->approved_by_user_id === $request->user()->id,
            403,
        );
    }

    private function snapshot(PurchaseOrder $order): array
    {
        return [
            'supplier_quote_id' => $order->supplier_quote_id,
            'supplier_id' => $order->supplier_id,
            'quote_currency_id' => $order->quote_currency_id,
            'base_currency_id' => $order->base_currency_id,
            'exchange_rate' => $order->exchange_rate,
            'quote_reference' => $order->quote_reference,
            'quote_date' => $order->quote_date?->toDateString(),
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'unit_cost_base' => $item->unit_cost_base,
                'tax_rate' => $item->tax_rate,
                'brand' => $item->brand,
                'delivery_days' => $item->delivery_days,
            ])->values()->all(),
        ];
    }

    private function quantityMilli(string $quantity): int
    {
        [$integer, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $integer * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    private function company(): Company
    {
        return Company::query()->where('singleton_key', 'primary')->firstOrFail();
    }

    private function audit(Request $request, int $companyId, string $action, PurchaseOrder $order, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'company_id' => $companyId,
            'action' => $action,
            'entity_type' => 'purchase_order',
            'entity_id' => $order->id,
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }
}
