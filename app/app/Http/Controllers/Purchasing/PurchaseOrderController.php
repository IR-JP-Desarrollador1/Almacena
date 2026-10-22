<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if (! $user->hasPermission('purchases.orders.view')
            && ! $user->hasPermission('purchases.quotes.generate_orders')
            && ! $user->hasPermission('purchases.orders.approve')) {
            $query->where('created_by_user_id', $user->id);
        }

        return view('purchasing.orders.index', ['orders' => $query->paginate(20)]);
    }

    public function create(Request $request): RedirectResponse
    {
        $requisitionId = $request->query('requisition_id');
        abort_unless(is_numeric($requisitionId) && (int) $requisitionId > 0, 404);

        return redirect()->route('purchasing.quotes.index', ['requisition' => (int) $requisitionId]);
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
                || $request->user()->hasPermission('purchases.quotes.generate_orders')
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
