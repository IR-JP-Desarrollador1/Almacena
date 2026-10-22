<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Support\DecimalMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            ->whereHas('requisition.department', fn (Builder $departments) => $departments->where('company_id', $companyId))
            ->with(['supplier', 'quoteCurrency', 'baseCurrency', 'requisition.warehouse.branch'])
            ->latest();

        if (! $user->hasGlobalWarehouseAccess()) {
            $query->whereHas(
                'requisition',
                fn (Builder $requisitions) => $requisitions->whereIn('warehouse_id', $user->accessibleWarehouses()->select('warehouses.id')),
            );
        }

        if (! $user->hasPermission('purchases.orders.view') && ! $user->hasPermission('purchases.orders.approve')) {
            $query->where('created_by_user_id', $user->id);
        }

        return view('purchasing.orders.index', ['orders' => $query->paginate(20)]);
    }

    public function create(Request $request): View
    {
        $company = $this->company()->load('baseCurrency');
        abort_unless($company->base_currency_id, 409, __('Configura la moneda base de la empresa antes de crear órdenes.'));
        $requisition = $this->approvedAccessibleRequisition((int) $request->query('requisition_id'), $request);
        abort_unless(! $requisition->purchaseOrder()->exists(), 409);

        return view('purchasing.orders.create', [
            'requisition' => $requisition->load(['items.product', 'items.unit', 'warehouse.branch']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('legal_name')->get(),
            'baseCurrency' => $company->baseCurrency,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requisition_id' => ['required', 'integer', 'exists:requisitions,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'quote_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['required', 'numeric', 'decimal:0,8', 'min:0.00000001', 'max:999999999.99999999'],
            'quote_reference' => ['required', 'string', 'max:100'],
            'quote_date' => ['required', 'date', 'before_or_equal:today'],
            'unit_costs' => ['required', 'array', 'min:1'],
            'unit_costs.*' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999.9999'],
        ]);
        $companyId = $this->company()->id;
        $user = $request->user();

        $order = DB::transaction(function () use ($validated, $request, $companyId, $user) {
            $company = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            abort_unless($company->base_currency_id, 409, __('Configura la moneda base de la empresa antes de crear órdenes.'));
            if (
                (int) $validated['quote_currency_id'] === (int) $company->base_currency_id
                && DecimalMath::multiply((string) $validated['exchange_rate'], 8, '1', 0, 8) !== '1.00000000'
            ) {
                throw ValidationException::withMessages([
                    'exchange_rate' => __('La tasa debe ser 1 cuando la moneda cotizada coincide con la moneda base.'),
                ]);
            }

            $requisition = Requisition::query()
                ->whereKey($validated['requisition_id'])
                ->where('status', Requisition::STATUS_APPROVED)
                ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $companyId))
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $requisition->warehouse_id !== null && $user->canAccessWarehouse($requisition->warehouse_id),
                403,
                __('No tienes acceso a la bodega de esta requisición.'),
            );
            abort_unless(! $requisition->purchaseOrder()->exists(), 409);

            $items = $requisition->items->keyBy(fn ($item) => (string) $item->id);
            $costs = $validated['unit_costs'];
            $submittedIds = array_map('strval', array_keys($costs));
            $expectedIds = $items->keys()->map(fn ($id) => (string) $id)->all();
            sort($submittedIds);
            sort($expectedIds);

            if ($submittedIds !== $expectedIds) {
                throw ValidationException::withMessages([
                    'unit_costs' => __('Se requiere un costo cotizado para cada producto de la requisición y no se permiten productos adicionales.'),
                ]);
            }

            $supplier = Supplier::query()->whereKey($validated['supplier_id'])->where('is_active', true)->firstOrFail();
            $quoteCurrency = Currency::query()->whereKey($validated['quote_currency_id'])->where('is_active', true)->firstOrFail();
            $order = PurchaseOrder::create([
                'reference' => 'TMP-'.Str::random(24),
                'requisition_id' => $requisition->id,
                'supplier_id' => $supplier->id,
                'quote_currency_id' => $quoteCurrency->id,
                'base_currency_id' => $company->base_currency_id,
                'exchange_rate' => $validated['exchange_rate'],
                'created_by_user_id' => $user->id,
                'quote_reference' => $validated['quote_reference'],
                'quote_date' => $validated['quote_date'],
                'status' => PurchaseOrder::STATUS_DRAFT,
            ]);
            $order->update(['reference' => 'OC-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'requisition_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_code' => $item->product_code,
                    'product_name' => $item->product_name,
                    'unit_abbreviation' => $item->unit_abbreviation,
                    'quantity' => $item->quantity,
                    'unit_cost' => $costs[$item->id],
                    'unit_cost_base' => DecimalMath::multiply(
                        (string) $costs[$item->id],
                        4,
                        (string) $validated['exchange_rate'],
                        8,
                        4,
                    ),
                ]);
            }

            $this->audit($request, $companyId, 'created', $order, [
                'requisition_id' => $requisition->id,
                'supplier_id' => $supplier->id,
                'quote_reference' => $order->quote_reference,
                'quote_date' => $order->quote_date->toDateString(),
                'quote_currency_id' => $quoteCurrency->id,
                'base_currency_id' => $company->base_currency_id,
                'exchange_rate' => $order->exchange_rate,
                'unit_costs' => $costs,
                'unit_costs_base' => $order->items()->pluck('unit_cost_base', 'requisition_item_id')->all(),
            ]);

            return $order;
        });

        return redirect()->route('purchasing.orders.show', $order)->with('status', __('Orden de compra creada como borrador; requiere aprobación antes de recibir.'));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'quoteCurrency',
            'baseCurrency',
            'creator',
            'approver',
            'requisition.department',
            'requisition.warehouse.branch',
            'items.product',
            'items.requisitionItem',
            'items.receiptItems',
            'receipts.items',
        ]);
        $this->authorizeOrderAccess($request, $purchaseOrder);

        return view('purchasing.orders.show', compact('purchaseOrder'));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('purchases.orders.approve'), 403);
        $companyId = $this->company()->id;

        DB::transaction(function () use ($request, $purchaseOrder, $companyId) {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->whereHas('requisition.department', fn (Builder $departments) => $departments->where('company_id', $companyId))
                ->with('requisition')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(
                $order->requisition->warehouse_id !== null
                    && $request->user()->canAccessWarehouse($order->requisition->warehouse_id),
                403,
            );
            abort_unless($order->status === PurchaseOrder::STATUS_DRAFT, 409);
            abort_unless($order->created_by_user_id !== $request->user()->id, 403, __('Quien crea la orden no puede aprobarla.'));

            $order->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $this->audit($request, $companyId, 'approved', $order, [
                'before' => ['status' => PurchaseOrder::STATUS_DRAFT],
                'after' => ['status' => PurchaseOrder::STATUS_APPROVED],
            ]);
        });

        return redirect()->route('purchasing.orders.show', $purchaseOrder)->with('status', __('Orden de compra aprobada; ya puede recibir productos.'));
    }

    private function approvedAccessibleRequisition(int $id, Request $request): Requisition
    {
        $requisition = Requisition::query()
            ->whereKey($id)
            ->where('status', Requisition::STATUS_APPROVED)
            ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $this->company()->id))
            ->with(['items.product', 'items.unit', 'warehouse.branch', 'purchaseOrder'])
            ->firstOrFail();
        abort_unless(
            $requisition->warehouse_id !== null && $request->user()->canAccessWarehouse($requisition->warehouse_id),
            403,
            __('No tienes acceso a la bodega de esta requisición.'),
        );

        return $requisition;
    }

    private function authorizeOrderAccess(Request $request, PurchaseOrder $order): void
    {
        abort_unless(
            $order->requisition->department->company_id === $this->company()->id,
            404,
        );
        abort_unless(
            $order->requisition->warehouse_id !== null
                && $request->user()->canAccessWarehouse($order->requisition->warehouse_id),
            403,
            __('No tienes acceso a la bodega asociada a esta orden.'),
        );
        abort_unless(
            $request->user()->hasPermission('purchases.orders.view')
                || $request->user()->hasPermission('purchases.orders.approve')
                || $order->created_by_user_id === $request->user()->id
                || $order->approved_by_user_id === $request->user()->id,
            403,
        );
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
