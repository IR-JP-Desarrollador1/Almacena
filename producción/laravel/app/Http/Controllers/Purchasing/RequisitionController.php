<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequisitionController extends Controller
{
    private const ITEM_AUDIT_FIELDS = ['product_id', 'product_code', 'product_name', 'unit_abbreviation', 'quantity', 'notes'];

    public function index(Request $request): View
    {
        $company = $this->company();
        $user = $request->user();
        $query = Requisition::query()
            ->whereHas('department', fn ($query) => $query->where('company_id', $company->id))
            ->whereIn('status', [Requisition::STATUS_PENDING_APPROVAL, Requisition::STATUS_APPROVED])
            ->with(['department', 'requester', 'approver', 'warehouse.branch'])
            ->latest();

        if ($user->hasGlobalWarehouseAccess()) {
            $query->where(fn (Builder $query) => $query
                ->whereNull('warehouse_id')
                ->orWhereHas('warehouse.branch', fn (Builder $branches) => $branches->where('company_id', $company->id)));
        } else {
            $query->whereIn('warehouse_id', $user->accessibleWarehouses()->select('warehouses.id'));
        }

        if (
            ! $user->hasPermission('purchases.requisitions.view')
            || (
                ($user->hasPermission('inventory.requisitions.manage') || $user->hasPermission('inventory.requisitions.submit'))
                && ! $user->hasPermission('purchases.requisitions.approve')
                && ! $user->hasPermission('purchases.requisitions.reject')
            )
        ) {
            $query->where(fn ($query) => $query->where('requester_user_id', $user->id)->orWhere('approver_user_id', $user->id));
        }

        return view('purchasing.requisitions.index', [
            'requisitions' => $query->paginate(20),
            'inventoryContext' => false,
        ]);
    }

    public function inventoryIndex(Request $request): View
    {
        $company = $this->company();
        $user = $request->user();
        $query = Requisition::query()
            ->whereHas('department', fn ($departments) => $departments->where('company_id', $company->id))
            ->whereIn('status', [
                Requisition::STATUS_DRAFT,
                Requisition::STATUS_ACTIVE,
                Requisition::STATUS_REJECTED,
                Requisition::STATUS_CANCELLED,
            ])
            ->whereIn('warehouse_id', $user->accessibleWarehouses()->select('warehouses.id'))
            ->with(['department', 'requester', 'approver', 'warehouse.branch'])
            ->latest();

        if (
            (
                ! $user->hasPermission('purchases.requisitions.view')
                && ! $user->hasPermission('inventory.requisitions.view')
            )
            || (
                ($user->hasPermission('inventory.requisitions.manage') || $user->hasPermission('inventory.requisitions.submit'))
                && ! $user->hasPermission('purchases.requisitions.approve')
                && ! $user->hasPermission('purchases.requisitions.reject')
            )
        ) {
            $query->where(fn (Builder $scope) => $scope
                ->where('requester_user_id', $user->id)
                ->when(
                    $user->hasPermission('inventory.issues.create')
                        || $user->hasPermission('inventory.requisitions.dispatch')
                        || $user->hasPermission('inventory.requisitions.view'),
                    fn (Builder $scope) => $scope->orWhere('status', Requisition::STATUS_ACTIVE),
                ));
        }

        return view('purchasing.requisitions.index', [
            'requisitions' => $query->paginate(20),
            'inventoryContext' => true,
        ]);
    }

    public function create(Request $request): View
    {
        $this->company();

        return view('purchasing.requisitions.form', [
            'requisition' => new Requisition,
            'department' => $request->user()->department()->with('manager')->first(),
            'warehouses' => $request->user()->accessibleWarehouses()->with('branch')->orderBy('name')->get(),
            'products' => $this->activeProducts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $department = $this->requestDepartment($request, $company);
        $validated = $this->validateRequisition($request);

        $requisition = DB::transaction(function () use ($request, $company, $validated, $department) {
            $requisition = Requisition::create([
                'reference' => 'TMP-'.Str::random(24),
                'department_id' => $department->id,
                'warehouse_id' => $validated['warehouse_id'],
                'requester_user_id' => $request->user()->id,
                'status' => Requisition::STATUS_DRAFT,
                'justification' => $validated['justification'],
                'needed_by' => $validated['needed_by'] ?? null,
            ]);
            $requisition->update(['reference' => 'REQ-'.str_pad((string) $requisition->id, 8, '0', STR_PAD_LEFT)]);
            $this->syncItems($requisition, $validated['items']);
            $this->audit($request, $company, 'created', $requisition);

            return $requisition;
        });

        $route = $request->routeIs('inventory.requisitions.store') ? 'inventory.requisitions.show' : 'purchasing.requisitions.show';

        return redirect()->route($route, $requisition)->with('status', __('Borrador de requisición creado.'));
    }

    public function show(Request $request, Requisition $requisition): View
    {
        $company = $this->company();
        $requisition = $this->inCompany($requisition->id, $company->id)->load([
            'department',
            'warehouse.branch',
            'requester',
            'approver',
            'warehouse',
            'purchaseOrders.supplier',
            'inventoryIssues',
            'items.product',
            'items.unit',
            'items.inventoryIssueItems',
            'items.award.quoteItem.quote.supplier',
        ]);
        $this->authorizeView($request, $requisition);

        return view('purchasing.requisitions.show', [
            'requisition' => $requisition,
            'inventoryContext' => $request->routeIs('inventory.requisitions.show'),
        ]);
    }

    public function edit(Request $request, Requisition $requisition): View|RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->inCompany($requisition->id, $company->id)->load('items');
        $this->authorizeRequester($request, $requisition);
        abort_unless(
            in_array($requisition->status, [Requisition::STATUS_DRAFT, Requisition::STATUS_REJECTED], true)
                && ! $requisition->inventoryIssues()->exists(),
            403,
        );

        return view('purchasing.requisitions.form', [
            'requisition' => $requisition,
            'department' => $requisition->department()->with('manager')->first(),
            'warehouses' => $request->user()->accessibleWarehouses()->with('branch')->orderBy('name')->get(),
            'products' => $this->activeProducts($requisition->items->pluck('product_id')->all()),
        ]);
    }

    public function update(Request $request, Requisition $requisition): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->inCompany($requisition->id, $company->id);
        $this->authorizeRequester($request, $requisition);
        abort_unless(
            in_array($requisition->status, [Requisition::STATUS_DRAFT, Requisition::STATUS_REJECTED], true)
                && ! $requisition->inventoryIssues()->exists(),
            403,
        );
        $validated = $this->validateRequisition($request);
        $department = $this->requestDepartment($request, $company);

        DB::transaction(function () use ($request, $company, $requisition, $validated, $department) {
            $before = $this->snapshot($requisition);
            $requisition->update([
                'department_id' => $department->id,
                'warehouse_id' => $validated['warehouse_id'],
                'justification' => $validated['justification'],
                'needed_by' => $validated['needed_by'] ?? null,
                'status' => Requisition::STATUS_DRAFT,
                'approver_user_id' => null,
                'rejection_reason' => null,
                'submitted_at' => null,
                'decided_at' => null,
            ]);
            $requisition->items()->delete();
            $this->syncItems($requisition, $validated['items']);
            $this->audit($request, $company, 'updated', $requisition, $before);
        });

        $route = $request->routeIs('inventory.requisitions.update') ? 'inventory.requisitions.show' : 'purchasing.requisitions.show';

        return redirect()->route($route, $requisition)->with('status', __('Borrador actualizado. Puedes volver a enviarlo a aprobación.'));
    }

    public function submit(Request $request, Requisition $requisition): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->inCompany($requisition->id, $company->id)->load('department.manager', 'items.inventoryIssueItems');
        $this->authorizeRequester($request, $requisition);
        abort_unless($requisition->status === Requisition::STATUS_ACTIVE, 403);
        abort_unless(
            $requisition->items->contains(fn (RequisitionItem $item) => DecimalMath::compare((string) $item->quantity, (string) $item->inventoryIssueItems->sum('quantity'), 3) > 0
            ),
            409,
            __('La requisición ya fue despachada por completo; no hay saldo para enviar a Compras.'),
        );

        $manager = $requisition->department->manager;
        if (
            ! $requisition->department->is_active
            || ! $manager
            || $manager->status !== User::STATUS_ACTIVE
            || $manager->id === $request->user()->id
            || ! $manager->hasPermission('purchases.requisitions.approve')
            || ! $manager->canAccessWarehouse((int) $requisition->warehouse_id)
        ) {
            return back()->withErrors(['approval' => __('El área debe tener un jefe activo con permiso de aprobación y acceso a la bodega, distinto del solicitante.')]);
        }

        DB::transaction(function () use ($request, $company, $requisition, $manager) {
            $lockedRequisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequisition->status === Requisition::STATUS_ACTIVE, 403);
            abort_unless(
                $lockedRequisition->items()
                    ->whereRaw('requisition_items.quantity > (SELECT COALESCE(SUM(inventory_issue_items.quantity), 0) FROM inventory_issue_items WHERE inventory_issue_items.requisition_item_id = requisition_items.id)')
                    ->exists(),
                409,
                __('La requisición ya fue despachada por completo; no hay saldo para enviar a Compras.'),
            );
            $requisition->update([
                'approver_user_id' => $manager->id,
                'status' => Requisition::STATUS_PENDING_APPROVAL,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);
            $this->audit($request, $company, 'submitted', $requisition);
        });

        return redirect()->route('purchasing.requisitions.show', $requisition)
            ->with('status', __('Requisición enviada al jefe del área para aprobación.'));
    }

    public function activate(Request $request, Requisition $requisition): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->inCompany($requisition->id, $company->id);
        $this->authorizeRequester($request, $requisition);

        DB::transaction(function () use ($request, $company, $requisition) {
            $requisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($requisition->status, [Requisition::STATUS_DRAFT, Requisition::STATUS_REJECTED], true)
                    && ! $requisition->inventoryIssues()->exists(),
                403,
            );
            abort_unless($requisition->items()->exists(), 409, __('Agrega al menos un producto antes de activar la requisición.'));

            $before = $this->snapshot($requisition);
            $requisition->update([
                'status' => Requisition::STATUS_ACTIVE,
                'approver_user_id' => null,
                'rejection_reason' => null,
                'submitted_at' => null,
                'decided_at' => null,
            ]);
            $this->audit($request, $company, 'activated', $requisition, $before);
        });

        return redirect()->route('inventory.requisitions.show', $requisition)
            ->with('status', __('Requisición activada; ya puedes registrar salidas parciales o enviarla a aprobación.'));
    }

    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->inCompany($requisition->id, $company->id);
        $this->authorizeRequester($request, $requisition);

        DB::transaction(function () use ($request, $company, $requisition) {
            $requisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                in_array($requisition->status, [
                    Requisition::STATUS_DRAFT,
                    Requisition::STATUS_ACTIVE,
                    Requisition::STATUS_REJECTED,
                ], true),
                403,
            );
            abort_unless(
                ! $requisition->inventoryIssues()->exists(),
                409,
                __('No se puede cancelar una requisición que ya tiene salidas registradas.'),
            );

            $before = $this->snapshot($requisition);
            $requisition->update(['status' => Requisition::STATUS_CANCELLED]);
            $this->audit($request, $company, 'cancelled', $requisition, $before);
        });

        return redirect()->route('inventory.requisitions.show', $requisition)
            ->with('status', __('Requisición cancelada.'));
    }

    public function approve(Request $request, Requisition $requisition): RedirectResponse
    {
        return $this->decide($request, $requisition, Requisition::STATUS_APPROVED, null);
    }

    public function reject(Request $request, Requisition $requisition): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return $this->decide($request, $requisition, Requisition::STATUS_REJECTED, $validated['rejection_reason']);
    }

    private function decide(Request $request, Requisition $requisition, string $status, ?string $reason): RedirectResponse
    {
        $company = $this->company();
        abort_unless(
            $request->user()->hasPermission($status === Requisition::STATUS_APPROVED
                ? 'purchases.requisitions.approve'
                : 'purchases.requisitions.reject')
                || ($status === Requisition::STATUS_APPROVED && $request->user()->hasPermission('purchases.requisitions.approve_any')),
            403,
        );
        abort_unless(
            $request->user()->canAccessWarehouse((int) $requisition->warehouse_id),
            403,
            __('No tienes acceso a la bodega asociada a esta requisición.'),
        );

        $resultingStatus = $status;
        DB::transaction(function () use ($request, $company, $requisition, $status, $reason, &$resultingStatus) {
            $requisition = Requisition::query()
                ->whereKey($requisition->id)
                ->whereHas('department', fn ($query) => $query->where('company_id', $company->id))
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $requisition->status === Requisition::STATUS_PENDING_APPROVAL
                    && (
                        $requisition->approver_user_id === $request->user()->id
                        || ($status === Requisition::STATUS_APPROVED
                            && $request->user()->hasPermission('purchases.requisitions.approve_any'))
                    )
                    && (
                        $requisition->requester_user_id !== $request->user()->id
                        || ($status === Requisition::STATUS_APPROVED
                            && $request->user()->hasPermission('purchases.requisitions.approve_any'))
                    ),
                403,
            );

            $hasIssues = $requisition->inventoryIssues()->exists();
            $resultingStatus = $status === Requisition::STATUS_REJECTED && $hasIssues
                ? Requisition::STATUS_ACTIVE
                : $status;
            $requisition->update([
                'status' => $resultingStatus,
                'rejection_reason' => $reason,
                'decided_at' => now(),
            ]);
            $this->audit($request, $company, $status, $requisition, ['status' => Requisition::STATUS_PENDING_APPROVAL, 'rejection_reason' => null]);
        });

        $message = $status === Requisition::STATUS_APPROVED
            ? __('Requisición aprobada.')
            : ($resultingStatus === Requisition::STATUS_ACTIVE
                ? __('La compra pendiente fue rechazada; la requisición vuelve a Inventario para reenvío.')
                : __('Requisición rechazada; el solicitante podrá corregirla y volver a enviarla.'));

        $route = $status === Requisition::STATUS_REJECTED
            ? 'inventory.requisitions.show'
            : 'purchasing.requisitions.show';

        return redirect()->route($route, $requisition)->with('status', $message);
    }

    private function validateRequisition(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => [
                'required',
                'integer',
                Rule::in($request->user()->accessibleWarehouses()->pluck('warehouses.id')->all()),
            ],
            'justification' => ['required', 'string', 'min:3', 'max:5000'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function syncItems(Requisition $requisition, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::query()->with('unit')->whereKey($item['product_id'])->where('is_active', true)->firstOrFail();
            abort_unless($product->unit->is_active, 422, __('El producto seleccionado debe tener una unidad activa.'));
            RequisitionItem::create([
                'requisition_id' => $requisition->id,
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_abbreviation' => $product->unit->abbreviation,
                'quantity' => $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function activeProducts(array $currentIds = [])
    {
        return Product::query()
            ->whereHas('unit', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->where('is_active', true)->when($currentIds, fn ($query) => $query->orWhereIn('id', $currentIds)))
            ->with('unit')
            ->orderBy('name')
            ->get();
    }

    private function company(): Company
    {
        return Company::query()->where('singleton_key', 'primary')->firstOrFail();
    }

    private function requestDepartment(Request $request, Company $company): Department
    {
        $department = $request->user()->department()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $department) {
            throw new HttpResponseException(
                back()->withErrors(['department' => __('Tu usuario debe estar asignado a un área activa antes de crear una requisición.')]),
            );
        }

        return $department;
    }

    private function inCompany(int $id, int $companyId): Requisition
    {
        return Requisition::query()->whereKey($id)
            ->whereHas('department', fn ($query) => $query->where('company_id', $companyId))
            ->firstOrFail();
    }

    private function authorizeRequester(Request $request, Requisition $requisition): void
    {
        abort_unless($requisition->requester_user_id === $request->user()->id, 403);
        abort_unless(
            $requisition->warehouse_id !== null
                && $request->user()->canAccessWarehouse($requisition->warehouse_id),
            403,
        );
    }

    private function authorizeView(Request $request, Requisition $requisition): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasGlobalWarehouseAccess()
                || ($requisition->warehouse_id !== null && $user->canAccessWarehouse($requisition->warehouse_id)),
            403,
        );
        abort_unless(
            $user->hasPermission('purchases.requisitions.view')
                || $requisition->requester_user_id === $user->id
                || $requisition->approver_user_id === $user->id
                || (
                    ! request()->routeIs('inventory.requisitions.show')
                        && $requisition->status === Requisition::STATUS_APPROVED
                        && (
                            $user->hasPermission('purchases.quotes.view')
                            || $user->hasPermission('purchases.quotes.manage')
                            || $user->hasPermission('purchases.quotes.award')
                            || $user->hasPermission('purchases.quotes.generate_orders')
                        )
                )
                || (
                    request()->routeIs('inventory.requisitions.show')
                        && in_array($requisition->status, [Requisition::STATUS_ACTIVE, Requisition::STATUS_APPROVED], true)
                        && ($user->hasPermission('inventory.issues.create') || $user->hasPermission('inventory.requisitions.dispatch') || $user->hasPermission('inventory.requisitions.view'))
                ),
            403,
        );
    }

    private function snapshot(Requisition $requisition): array
    {
        return [
            'department_id' => $requisition->department_id,
            'warehouse_id' => $requisition->warehouse_id,
            'justification' => $requisition->justification,
            'needed_by' => $requisition->needed_by?->toDateString(),
            'status' => $requisition->status,
            'items' => $requisition->items()->get()->map->only(self::ITEM_AUDIT_FIELDS)->all(),
        ];
    }

    private function audit(
        Request $request,
        Company $company,
        string $action,
        Requisition $requisition,
        ?array $before = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'company_id' => $company->id,
            'action' => $action,
            'entity_type' => 'requisition',
            'entity_id' => $requisition->id,
            'changes' => [
                'before' => $before,
                'after' => $this->snapshot($requisition),
            ],
            'ip_address' => $request->ip(),
        ]);
    }
}
