<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\DecimalMath;
use App\Support\RegionalFormatter;
use App\Support\ReportFileExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function inventory(Request $request): View|StreamedResponse
    {
        $company = $this->company();
        $warehouseQuery = $request->user()->accessibleWarehouses()
            ->whereHas('branch', fn (Builder $branches) => $branches->where('company_id', $company->id))
            ->with(['branch', 'costCenter'])
            ->orderBy('name');
        $allWarehouseIds = (clone $warehouseQuery)->pluck('warehouses.id')->all();
        $costCenterIds = (clone $warehouseQuery)->whereNotNull('cost_center_id')->distinct()->pluck('cost_center_id')->all();
        $productIds = StockMovement::query()
            ->whereIn('warehouse_id', $allWarehouseIds)
            ->distinct()
            ->pluck('product_id')
            ->all();

        $filters = $request->validate([
            'report' => ['nullable', Rule::in(['movements', 'stock', 'adjustments'])],
            'movement' => ['nullable', Rule::in(['all', 'receipts', 'issues', 'transfers'])],
            'warehouse_id' => ['nullable', 'integer', Rule::in($allWarehouseIds)],
            'product_id' => ['nullable', 'integer', Rule::in($productIds)],
            'cost_center_id' => ['nullable', 'integer', Rule::in($costCenterIds)],
            'from_date' => ['nullable', 'date', 'before_or_equal:today'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date', 'before_or_equal:today'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);
        $warehouseIds = isset($filters['cost_center_id'])
            ? (clone $warehouseQuery)->where('cost_center_id', $filters['cost_center_id'])->pluck('warehouses.id')->all()
            : $allWarehouseIds;
        $report = $filters['report'] ?? 'movements';
        $warehouseId = $filters['warehouse_id'] ?? null;
        $productId = $filters['product_id'] ?? null;

        $rows = match ($report) {
            'stock' => $this->stockRows($warehouseIds, $warehouseId, $productId, ! isset($filters['format'])),
            'adjustments' => $this->adjustmentRows($company->id, $warehouseIds, $filters, ! isset($filters['format'])),
            default => $this->movementRows($company->id, $warehouseIds, $filters, ! isset($filters['format'])),
        };

        if (isset($filters['format'])) {
            return $this->exportInventory($rows, $report, $filters['format'], $company->baseCurrency?->code);
        }

        return view('reports.inventory', [
            'report' => $report,
            'filters' => $filters,
            'rows' => $rows,
            'warehouses' => $warehouseQuery->get(),
            'costCenters' => $company->costCenters()->whereIn('id', $costCenterIds)->orderBy('code')->get(),
            'products' => Product::query()->whereIn('id', $productIds)->orderBy('code')->get(['id', 'code', 'name']),
            'regional' => RegionalFormatter::fromCompany($company),
            'baseCurrency' => $company->baseCurrency?->code,
        ]);
    }

    public function purchasing(Request $request): View|StreamedResponse
    {
        $company = $this->company();
        $costCenterIds = $company->costCenters()->pluck('id')->all();
        $supplierIds = Supplier::query()->pluck('id')->all();
        $report = $request->validate([
            'report' => ['nullable', Rule::in(['orders', 'spend', 'requisitions'])],
        ])['report'] ?? 'orders';
        $statusValues = match ($report) {
            'requisitions' => [
                Requisition::STATUS_DRAFT,
                Requisition::STATUS_ACTIVE,
                Requisition::STATUS_PENDING_APPROVAL,
                Requisition::STATUS_APPROVED,
                Requisition::STATUS_REJECTED,
                Requisition::STATUS_CANCELLED,
            ],
            'orders' => [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_PENDING_APPROVAL,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_REJECTED,
            ],
            default => [],
        };
        $filters = $request->validate([
            'report' => ['nullable', Rule::in(['orders', 'spend', 'requisitions'])],
            'status' => ['nullable', Rule::in($statusValues)],
            'supplier_id' => ['nullable', 'integer', Rule::in($supplierIds)],
            'cost_center_id' => ['nullable', 'integer', Rule::in($costCenterIds)],
            'from_date' => ['nullable', 'date', 'before_or_equal:today'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date', 'before_or_equal:today'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);
        $report = $filters['report'] ?? 'orders';
        $paginate = ! isset($filters['format']);
        $rows = match ($report) {
            'spend' => $this->spendRows($request, $company->id, $filters, $paginate),
            'requisitions' => $this->requisitionRows($request, $company->id, $filters, $paginate),
            default => $this->orderRows($request, $company->id, $filters, $paginate),
        };

        if (isset($filters['format'])) {
            return $this->exportPurchasing($rows, $report, $filters['format'], $company->baseCurrency?->code);
        }

        return view('reports.purchasing', [
            'report' => $report,
            'filters' => $filters,
            'rows' => $rows,
            'suppliers' => Supplier::query()->orderBy('legal_name')->get(['id', 'legal_name']),
            'costCenters' => $company->costCenters()->orderBy('code')->get(),
            'regional' => RegionalFormatter::fromCompany($company),
            'baseCurrency' => $company->baseCurrency,
        ]);
    }

    private function movementRows(int $companyId, array $warehouseIds, array $filters, bool $paginate = true)
    {
        $query = StockMovement::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
            ->with(['warehouse.branch', 'warehouse.costCenter', 'product', 'inventoryIssue'])
            ->when(isset($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['product_id']), fn (Builder $query) => $query->where('product_id', $filters['product_id']))
            ->when(isset($filters['from_date']), fn (Builder $query) => $query->whereDate('occurred_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn (Builder $query) => $query->whereDate('occurred_at', '<=', $filters['to_date']))
            ->when(($filters['movement'] ?? 'all') === 'receipts', fn (Builder $query) => $query
                ->where('movement_type', StockMovement::TYPE_RECEIPT)
                ->where('reference_type', '!=', 'inventory_transfer'))
            ->when(($filters['movement'] ?? 'all') === 'issues', fn (Builder $query) => $query
                ->where('movement_type', StockMovement::TYPE_ISSUE)
                ->where('reference_type', '!=', 'inventory_transfer'))
            ->when(($filters['movement'] ?? 'all') === 'transfers', fn (Builder $query) => $query
                ->where('reference_type', 'inventory_transfer'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $rows = $paginate ? $query->paginate(100)->withQueryString() : $query->get();
        foreach ($rows as $movement) {
            $movement->total_cost_base = DecimalMath::multiply(
                (string) $movement->quantity,
                3,
                (string) $movement->unit_cost_base,
                4,
                2,
            );
        }

        return $rows;
    }

    private function stockRows(array $warehouseIds, ?int $warehouseId, ?int $productId, bool $paginate = true)
    {
        $receipt = StockMovement::TYPE_RECEIPT;
        $query = DB::table('stock_movements as movements')
            ->join('warehouses', 'warehouses.id', '=', 'movements.warehouse_id')
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->leftJoin('cost_centers', 'cost_centers.id', '=', 'warehouses.cost_center_id')
            ->join('products', 'products.id', '=', 'movements.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->whereIn('movements.warehouse_id', $warehouseIds)
            ->when($warehouseId, fn ($query) => $query->where('movements.warehouse_id', $warehouseId))
            ->when($productId, fn ($query) => $query->where('movements.product_id', $productId))
            ->select([
                'movements.warehouse_id',
                'movements.product_id',
                'warehouses.name as warehouse_name',
                'branches.name as branch_name',
                'cost_centers.code as cost_center_code',
                'products.code as product_code',
                'products.name as product_name',
                'units.abbreviation as unit_abbreviation',
            ])
            ->selectRaw(
                'SUM(CASE WHEN movements.movement_type = ? THEN movements.quantity ELSE -movements.quantity END) as current_quantity',
                [$receipt],
            )
            ->selectRaw(
                'SUM(CASE WHEN movements.movement_type = ? THEN movements.quantity * movements.unit_cost_base ELSE -movements.quantity * movements.unit_cost_base END) as current_value',
                [$receipt],
            )
            ->groupBy(
                'movements.warehouse_id',
                'movements.product_id',
                'warehouses.name',
                'branches.name',
                'cost_centers.code',
                'products.code',
                'products.name',
                'units.abbreviation',
            )
            ->havingRaw(
                'SUM(CASE WHEN movements.movement_type = ? THEN movements.quantity ELSE -movements.quantity END) > 0',
                [$receipt],
            )
            ->orderBy('warehouses.name')
            ->orderBy('products.code');

        return $paginate ? $query->paginate(100)->withQueryString() : $query->get();
    }

    private function adjustmentRows(int $companyId, array $warehouseIds, array $filters, bool $paginate = true)
    {
        $query = InventoryCountItem::query()
            ->where('difference_quantity', '!=', 0)
            ->when(isset($filters['product_id']), fn (Builder $items) => $items->where('product_id', $filters['product_id']))
            ->whereHas('inventoryCount', function (Builder $counts) use ($companyId, $warehouseIds, $filters) {
                $counts->where('status', 'approved')
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
                    ->when(isset($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
                    ->when(isset($filters['from_date']), fn (Builder $query) => $query->whereDate('approved_at', '>=', $filters['from_date']))
                    ->when(isset($filters['to_date']), fn (Builder $query) => $query->whereDate('approved_at', '<=', $filters['to_date']));
            })
            ->with(['inventoryCount.warehouse.branch', 'inventoryCount.warehouse.costCenter', 'product'])
            ->orderByDesc('created_at');

        return $paginate ? $query->paginate(100)->withQueryString() : $query->get();
    }

    private function orderRows(Request $request, int $companyId, array $filters, bool $paginate = true)
    {
        $query = $this->purchaseOrders($request, $companyId)
            ->with(['supplier', 'warehouse', 'requisition.department.costCenter', 'items'])
            ->when(isset($filters['status']), fn (Builder $orders) => $orders->where('status', $filters['status']))
            ->when(isset($filters['supplier_id']), fn (Builder $orders) => $orders->where('supplier_id', $filters['supplier_id']))
            ->when(isset($filters['cost_center_id']), fn (Builder $orders) => $orders->whereHas(
                'requisition.department',
                fn (Builder $departments) => $departments->where('cost_center_id', $filters['cost_center_id']),
            ))
            ->when(isset($filters['from_date']), fn (Builder $orders) => $orders->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn (Builder $orders) => $orders->whereDate('created_at', '<=', $filters['to_date']))
            ->orderByDesc('created_at');
        $rows = $paginate ? $query->paginate(50)->withQueryString() : $query->get();
        foreach ($rows as $order) {
            $order->estimated_total_base = $order->items->sum(
                fn ($item) => (float) $item->quantity
                    * (float) $item->unit_cost_base
                    * (1 + (float) $item->tax_rate / 100),
            );
        }

        return $rows;
    }

    private function spendRows(Request $request, int $companyId, array $filters, bool $paginate = true): array
    {
        $query = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', function (Builder $orders) use ($request, $companyId, $filters) {
                $this->applyPurchaseOrderScope($orders, $request, $companyId);
                $orders->where('status', PurchaseOrder::STATUS_APPROVED)
                    ->when(isset($filters['supplier_id']), fn (Builder $query) => $query->where('supplier_id', $filters['supplier_id']))
                    ->when(isset($filters['cost_center_id']), fn (Builder $query) => $query->whereHas(
                        'requisition.department',
                        fn (Builder $departments) => $departments->where('cost_center_id', $filters['cost_center_id']),
                    ))
                    ->when(isset($filters['from_date']), fn (Builder $query) => $query->whereDate('approved_at', '>=', $filters['from_date']))
                    ->when(isset($filters['to_date']), fn (Builder $query) => $query->whereDate('approved_at', '<=', $filters['to_date']));
            })
            ->with(['purchaseOrder.supplier', 'purchaseOrder.requisition.department.costCenter', 'product'])
            ->orderBy('purchase_order_id')
            ->orderBy('id');
        $total = (clone $query)->sum(DB::raw(
            'purchase_order_items.quantity * purchase_order_items.unit_cost_base * (1 + purchase_order_items.tax_rate / 100)',
        ));

        return [
            'items' => $paginate ? $query->paginate(100)->withQueryString() : $query->get(),
            'total' => $total,
        ];
    }

    private function requisitionRows(Request $request, int $companyId, array $filters, bool $paginate = true)
    {
        $query = Requisition::query()
            ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $companyId))
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
            ->when(
                ! $request->user()->hasGlobalWarehouseAccess(),
                fn (Builder $query) => $query->whereIn(
                    'warehouse_id',
                    $request->user()->accessibleWarehouses()->select('warehouses.id'),
                ),
            )
            ->with(['department.costCenter', 'warehouse.branch', 'requester'])
            ->withCount('items')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['cost_center_id']), fn (Builder $query) => $query->whereHas(
                'department',
                fn (Builder $departments) => $departments->where('cost_center_id', $filters['cost_center_id']),
            ))
            ->when(isset($filters['from_date']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->orderByDesc('created_at');

        return $paginate ? $query->paginate(50)->withQueryString() : $query->get();
    }

    private function exportInventory(iterable $rows, string $report, string $format, ?string $baseCurrency): StreamedResponse
    {
        [$headings, $exportRows, $slug] = match ($report) {
            'stock' => [
                [__('Bodega'), __('Centro de costo'), __('Código de producto'), __('Producto'), __('Existencia actual'), __('Unidad'), __('Valor actual'), __('Moneda')],
                collect($rows)->map(fn ($row) => [
                    $row->branch_name.' — '.$row->warehouse_name,
                    $row->cost_center_code ?? '',
                    $row->product_code,
                    $row->product_name,
                    (float) $row->current_quantity,
                    $row->unit_abbreviation,
                    (float) $row->current_value,
                    $baseCurrency ?? '',
                ]),
                'existencias',
            ],
            'adjustments' => [
                [__('Fecha de aprobación'), __('Conteo'), __('Bodega'), __('Centro de costo'), __('Código de producto'), __('Producto'), __('Existencia teórica'), __('Cantidad contada'), __('Diferencia'), __('Tipo de ajuste')],
                collect($rows)->map(fn ($item) => [
                    $item->inventoryCount->approved_at?->toDateTimeString() ?? '',
                    $item->inventoryCount->reference,
                    $item->inventoryCount->warehouse->name,
                    $item->inventoryCount->warehouse->costCenter?->code ?? '',
                    $item->product_code,
                    $item->product_name,
                    (float) $item->system_quantity,
                    (float) $item->counted_quantity,
                    (float) $item->difference_quantity,
                    (float) $item->difference_quantity > 0 ? __('Sobrante') : __('Faltante'),
                ]),
                'ajustes',
            ],
            default => [
                [__('Fecha'), __('Bodega'), __('Centro de costo'), __('Código de producto'), __('Producto'), __('Movimiento'), __('Cantidad'), __('Costo unitario base'), __('Costo total'), __('Moneda'), __('Documento')],
                collect($rows)->map(fn ($movement) => [
                    $movement->occurred_at?->toDateTimeString() ?? '',
                    $movement->warehouse->branch->name.' — '.$movement->warehouse->name,
                    $movement->warehouse->costCenter?->code ?? '',
                    $movement->product->code,
                    $movement->product->name,
                    $movement->reference_type === 'inventory_transfer'
                        ? __('Traslado')
                        : ($movement->movement_type === StockMovement::TYPE_RECEIPT ? __('Entrada') : __('Salida')),
                    (float) $movement->quantity,
                    (float) $movement->unit_cost_base,
                    (float) $movement->total_cost_base,
                    $baseCurrency ?? '',
                    $movement->reference_type === 'inventory_transfer'
                        ? $movement->inventoryIssue?->reference
                        : $movement->reference_type.' #'.$movement->reference_id,
                ]),
                'movimientos',
            ],
        };

        $numberPrecisions = match ($report) {
            'stock' => [4 => 0, 6 => 2],
            'adjustments' => [6 => 0, 7 => 0, 8 => 0],
            default => [6 => 0, 7 => 2, 8 => 2],
        };

        return (new ReportFileExporter)->download(
            "reporte-inventario-{$slug}-".today()->format('Ymd').".{$format}",
            $headings,
            $exportRows,
            $format,
            $numberPrecisions,
        );
    }

    private function exportPurchasing(array|Collection $rows, string $report, string $format, ?string $baseCurrency): StreamedResponse
    {
        [$headings, $exportRows, $slug] = match ($report) {
            'spend' => [
                [__('Orden'), __('Proveedor'), __('Código de producto'), __('Producto'), __('Centro de costo'), __('Cantidad'), __('Costo unitario base'), __('Tasa de impuesto (%)'), __('Total de línea'), __('Moneda')],
                $rows['items']->map(fn ($item) => [
                    $item->purchaseOrder->reference,
                    $item->purchaseOrder->supplier->legal_name,
                    $item->product_code,
                    $item->product_name,
                    $item->purchaseOrder->requisition?->department?->costCenter?->code ?? '',
                    (float) $item->quantity,
                    (float) $item->unit_cost_base,
                    (float) $item->tax_rate,
                    (float) $item->quantity * (float) $item->unit_cost_base * (1 + (float) $item->tax_rate / 100),
                    $baseCurrency ?? '',
                ]),
                'compras-aprobadas',
            ],
            'requisitions' => [
                [__('Requisición'), __('Área'), __('Centro de costo'), __('Bodega'), __('Solicitante'), __('Estado'), __('Fecha de creación'), __('Productos')],
                collect($rows)->map(fn ($requisition) => [
                    $requisition->reference,
                    $requisition->department->name,
                    $requisition->department->costCenter?->code ?? '',
                    $requisition->warehouse->name,
                    trim($requisition->requester->name.' '.$requisition->requester->last_name),
                    __('requisition_status.'.$requisition->status),
                    $requisition->created_at?->toDateString() ?? '',
                    (int) $requisition->items_count,
                ]),
                'requisiciones',
            ],
            default => [
                [__('Orden'), __('Proveedor'), __('Bodega'), __('Centro de costo'), __('Estado'), __('Fecha de creación'), __('Total estimado'), __('Moneda')],
                collect($rows)->map(fn ($order) => [
                    $order->reference,
                    $order->supplier->legal_name,
                    $order->warehouse->name,
                    $order->requisition?->department?->costCenter?->code ?? '',
                    __('order_status.'.$order->status),
                    $order->created_at?->toDateString() ?? '',
                    (float) $order->estimated_total_base,
                    $baseCurrency ?? '',
                ]),
                'ordenes',
            ],
        };

        $numberPrecisions = match ($report) {
            'spend' => [5 => 0, 6 => 2, 7 => 2, 8 => 2],
            'requisitions' => [7 => 0],
            default => [6 => 2],
        };

        return (new ReportFileExporter)->download(
            "reporte-compras-{$slug}-".today()->format('Ymd').".{$format}",
            $headings,
            $exportRows,
            $format,
            $numberPrecisions,
        );
    }

    private function purchaseOrders(Request $request, int $companyId): Builder
    {
        $query = PurchaseOrder::query()
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId));
        $this->applyPurchaseOrderScope($query, $request, $companyId);

        return $query;
    }

    private function applyPurchaseOrderScope(Builder $query, Request $request, int $companyId): void
    {
        $query->whereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $companyId))
            ->when(
                ! $request->user()->hasGlobalWarehouseAccess(),
                fn (Builder $orders) => $orders->whereIn(
                    'warehouse_id',
                    $request->user()->accessibleWarehouses()->select('warehouses.id'),
                ),
            );
    }

    private function company(): Company
    {
        return Company::query()->where('singleton_key', 'primary')->firstOrFail();
    }
}
