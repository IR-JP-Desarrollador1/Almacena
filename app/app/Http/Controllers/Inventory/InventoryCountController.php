<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLot;
use App\Support\DecimalMath;
use App\Support\RegionalFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryCountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = InventoryCount::query()
            ->whereHas('warehouse.branch', fn (Builder $branches) => $branches
                ->where('company_id', $this->company()->id))
            ->with(['warehouse.branch', 'creator'])
            ->withCount('items')
            ->latest();
        $this->scopeWarehouses($query, $user);
        if (! $user->hasPermission('inventory.counts.view') && ! $user->hasPermission('inventory.counts.approve')) {
            $query->where('created_by_user_id', $user->id);
        }

        return view('inventory.counts.index', [
            'counts' => $query->paginate(20),
            'canCreate' => $user->hasPermission('inventory.counts.create'),
            'regional' => RegionalFormatter::fromCompany($this->company()),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.counts.create'), 403);

        return $this->countForm($request, new InventoryCount([
            'warehouse_id' => old('warehouse_id'),
        ]), collect(old('items', [[]])));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.counts.create'), 403);
        $validated = $this->validateCount($request);
        $companyId = $this->company()->id;
        $user = $request->user();

        $count = DB::transaction(function () use ($validated, $companyId, $user, $request) {
            $warehouse = $this->accessibleWarehouse((int) $validated['warehouse_id'], $user, $companyId);
            $products = $this->activeProducts($validated['items']);
            $count = InventoryCount::query()->create([
                'reference' => 'TMP-'.Str::random(24),
                'warehouse_id' => $warehouse->id,
                'status' => InventoryCount::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);
            $count->update(['reference' => 'CNT-'.str_pad((string) $count->id, 8, '0', STR_PAD_LEFT)]);
            $this->replaceItems($count, $validated['items'], $products);
            $this->audit($request, $companyId, 'created', $count, [
                'warehouse_id' => $warehouse->id,
                'items' => $count->items()->get(['product_id', 'counted_quantity'])->toArray(),
            ]);

            return $count;
        });

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', __('Conteo guardado como borrador.'));
    }

    public function edit(Request $request, InventoryCount $count): View
    {
        $this->authorizeDraft($request, $count);
        $count->load('items');

        return $this->countForm($request, $count, $count->items);
    }

    public function update(Request $request, InventoryCount $count): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.counts.create'), 403);
        $validated = $this->validateCount($request);
        $companyId = $this->company()->id;
        $user = $request->user();

        DB::transaction(function () use ($validated, $count, $companyId, $user, $request) {
            $lockedCount = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraftOwner($lockedCount, $user->id);
            $warehouse = $this->accessibleWarehouse((int) $validated['warehouse_id'], $user, $companyId);
            $products = $this->activeProducts($validated['items']);
            $before = $lockedCount->items()->get(['product_id', 'counted_quantity'])->toArray();
            $lockedCount->update([
                'warehouse_id' => $warehouse->id,
                'return_reason' => null,
            ]);
            $lockedCount->items()->delete();
            $this->replaceItems($lockedCount, $validated['items'], $products);
            $this->audit($request, $companyId, 'updated', $lockedCount, [
                'before' => $before,
                'items' => $lockedCount->items()->get(['product_id', 'counted_quantity'])->toArray(),
            ]);
        });

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', __('Borrador de conteo actualizado.'));
    }

    public function show(Request $request, InventoryCount $count): View
    {
        $this->authorizeView($request, $count);
        $count->load(['warehouse.branch', 'creator', 'approver', 'items.product']);
        foreach ($count->items as $item) {
            $item->requires_manual_cost = false;
            $item->suggested_surplus_cost = null;
            if (
                $count->status === InventoryCount::STATUS_PENDING_APPROVAL
                && (int) round(((float) $item->difference_quantity) * 1000) > 0
            ) {
                $cost = $this->surplusUnitCost($count->warehouse_id, $item->product_id);
                $item->suggested_surplus_cost = $cost;
                $item->requires_manual_cost = $cost === null;
            }
        }

        return view('inventory.counts.show', [
            'count' => $count,
            'regional' => RegionalFormatter::fromCompany($this->company()),
            'canEdit' => $count->status === InventoryCount::STATUS_DRAFT
                && $count->created_by_user_id === $request->user()->id
                && $request->user()->hasPermission('inventory.counts.create'),
            'canApprove' => $count->status === InventoryCount::STATUS_PENDING_APPROVAL
                && $count->created_by_user_id !== $request->user()->id
                && $request->user()->hasPermission('inventory.counts.approve'),
        ]);
    }

    public function submit(Request $request, InventoryCount $count): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.counts.create'), 403);
        $companyId = $this->company()->id;
        $user = $request->user();

        DB::transaction(function () use ($count, $companyId, $user, $request) {
            $lockedCount = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraftOwner($lockedCount, $user->id);
            $this->accessibleWarehouse($lockedCount->warehouse_id, $user, $companyId, true);
            $items = $lockedCount->items()->with('product')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => __('Agrega al menos un producto antes de enviar el conteo.'),
                ]);
            }

            foreach ($items as $item) {
                $systemMilli = $this->stockBalanceMilli($lockedCount->warehouse_id, $item->product_id, true);
                $countedMilli = $this->quantityMilli($item->counted_quantity);
                $item->update([
                    'system_quantity' => $this->quantityString($systemMilli),
                    'movement_id_snapshot' => StockMovement::query()
                        ->where('warehouse_id', $lockedCount->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->max('id'),
                    'difference_quantity' => $this->quantityString($countedMilli - $systemMilli),
                ]);
            }

            $lockedCount->update([
                'status' => InventoryCount::STATUS_PENDING_APPROVAL,
                'submitted_at' => now(),
                'return_reason' => null,
            ]);
            $this->audit($request, $companyId, 'submitted_for_approval', $lockedCount, [
                'status' => InventoryCount::STATUS_PENDING_APPROVAL,
                'items' => $lockedCount->items()->get([
                    'product_id',
                    'system_quantity',
                    'counted_quantity',
                    'difference_quantity',
                ])->toArray(),
            ]);
        });

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', __('Conteo enviado a aprobación.'));
    }

    public function approve(Request $request, InventoryCount $count): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.counts.approve'), 403);
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.surplus_unit_cost' => ['nullable', 'numeric', 'decimal:0,4', 'gt:0', 'max:99999999999.9999'],
            'items.*.expires_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $companyId = $this->company()->id;
        $user = $request->user();

        DB::transaction(function () use ($request, $count, $validated, $companyId, $user) {
            $lockedCount = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedCount->status === InventoryCount::STATUS_PENDING_APPROVAL, 409);
            abort_unless($lockedCount->created_by_user_id !== $user->id, 403);
            $this->accessibleWarehouse($lockedCount->warehouse_id, $user, $companyId, true);
            $warehouse = Warehouse::query()->whereKey($lockedCount->warehouse_id)->lockForUpdate()->firstOrFail();
            $items = $lockedCount->items()->with('product')->lockForUpdate()->get();
            $inputs = $validated['items'] ?? [];

            foreach ($items as $item) {
                $hasNewMovements = StockMovement::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->when(
                        $item->movement_id_snapshot !== null,
                        fn (Builder $query) => $query->where('id', '>', $item->movement_id_snapshot),
                    )
                    ->exists();
                $currentMilli = $this->stockBalanceMilli($warehouse->id, $item->product_id, true);
                $snapshotMilli = $this->quantityMilli($item->system_quantity);
                if ($hasNewMovements || $currentMilli !== $snapshotMilli) {
                    throw ValidationException::withMessages([
                        'approval' => __('Las existencias cambiaron después del envío. Devuelve el conteo al borrador y vuelve a contar antes de aprobar.'),
                    ]);
                }

                $differenceMilli = $this->quantityMilli($item->difference_quantity);
                if ($differenceMilli <= 0) {
                    continue;
                }

                $lineInput = $inputs[$item->id] ?? [];
                $suggestedCost = $this->surplusUnitCost($warehouse->id, $item->product_id);
                $unitCost = $suggestedCost ?? ($lineInput['surplus_unit_cost'] ?? null);
                if ($unitCost === null) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.surplus_unit_cost" => __('Indica el costo unitario: no hay existencias ni costos históricos para calcular el promedio.'),
                    ]);
                }

                $expiresOn = $lineInput['expires_on'] ?? null;
                if ($item->product->tracks_expiration && ! $expiresOn) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.expires_on" => __('Este producto requiere fecha de vencimiento para registrar el sobrante.'),
                    ]);
                }
                if (! $item->product->tracks_expiration && $expiresOn) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.expires_on" => __('No registres vencimiento para un producto que no controla fecha de expiración.'),
                    ]);
                }
            }

            $approvedAt = now();
            foreach ($items as $item) {
                $differenceMilli = $this->quantityMilli($item->difference_quantity);
                if ($differenceMilli === 0) {
                    continue;
                }

                if ($differenceMilli > 0) {
                    $lineInput = $inputs[$item->id] ?? [];
                    $unitCost = $this->surplusUnitCost($warehouse->id, $item->product_id)
                        ?? $lineInput['surplus_unit_cost'];
                    $this->receiveSurplus(
                        $lockedCount,
                        $warehouse,
                        $item,
                        $differenceMilli,
                        $unitCost,
                        $lineInput['expires_on'] ?? null,
                        $user->id,
                        $approvedAt,
                    );
                    $item->update([
                        'surplus_unit_cost' => $unitCost,
                        'expires_on' => $lineInput['expires_on'] ?? null,
                    ]);

                    continue;
                }

                $this->issueShortage(
                    $lockedCount,
                    $warehouse,
                    $item,
                    abs($differenceMilli),
                    $user->id,
                    $approvedAt,
                );
            }

            $lockedCount->update([
                'status' => InventoryCount::STATUS_APPROVED,
                'approved_by_user_id' => $user->id,
                'approved_at' => $approvedAt,
            ]);
            $this->audit($request, $companyId, 'approved', $lockedCount, [
                'status' => InventoryCount::STATUS_APPROVED,
                'items' => $lockedCount->items()->get([
                    'product_id',
                    'system_quantity',
                    'counted_quantity',
                    'difference_quantity',
                    'surplus_unit_cost',
                    'expires_on',
                ])->toArray(),
            ]);
        });

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', __('Conteo aprobado y ajustes registrados.'));
    }

    public function returnToDraft(Request $request, InventoryCount $count): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.counts.approve'), 403);
        $validated = $request->validate([
            'return_reason' => ['required', 'string', 'max:500'],
        ]);
        $companyId = $this->company()->id;
        $user = $request->user();

        DB::transaction(function () use ($count, $validated, $companyId, $user, $request) {
            $lockedCount = InventoryCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedCount->status === InventoryCount::STATUS_PENDING_APPROVAL, 409);
            abort_unless($lockedCount->created_by_user_id !== $user->id, 403);
            $this->accessibleWarehouse($lockedCount->warehouse_id, $user, $companyId, true);
            $lockedCount->items()->update([
                'system_quantity' => null,
                'movement_id_snapshot' => null,
                'difference_quantity' => null,
                'surplus_unit_cost' => null,
                'expires_on' => null,
            ]);
            $lockedCount->update([
                'status' => InventoryCount::STATUS_DRAFT,
                'submitted_at' => null,
                'return_reason' => $validated['return_reason'],
            ]);
            $this->audit($request, $companyId, 'returned_to_draft', $lockedCount, [
                'return_reason' => $validated['return_reason'],
            ]);
        });

        return redirect()->route('inventory.counts.show', $count)
            ->with('status', __('Conteo devuelto al borrador para corrección.'));
    }

    private function countForm(Request $request, InventoryCount $count, $items): View
    {
        $user = $request->user();
        $warehouses = $user->accessibleWarehouses()
            ->with('branch')
            ->orderBy('name')
            ->get();
        $products = Product::query()
            ->where('is_active', true)
            ->with('unit')
            ->orderBy('code')
            ->get();

        return view('inventory.counts.form', [
            'count' => $count,
            'warehouses' => $warehouses,
            'products' => $products,
            'items' => $items,
            'regional' => RegionalFormatter::fromCompany($this->company()),
        ]);
    }

    private function validateCount(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.counted_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
        ]);
    }

    private function activeProducts(array $items)
    {
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id'))
            ->where('is_active', true)
            ->with('unit')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($products->count() !== count($items)) {
            throw ValidationException::withMessages([
                'items' => __('Selecciona únicamente productos activos del catálogo.'),
            ]);
        }

        return $products;
    }

    private function replaceItems(InventoryCount $count, array $items, $products): void
    {
        foreach ($items as $item) {
            $product = $products[(int) $item['product_id']];
            $count->items()->create([
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_abbreviation' => $product->unit->abbreviation,
                'counted_quantity' => $item['counted_quantity'],
            ]);
        }
    }

    private function ensureDraftOwner(InventoryCount $count, int $userId): void
    {
        abort_unless(
            $count->status === InventoryCount::STATUS_DRAFT
                && $count->created_by_user_id === $userId,
            403,
        );
    }

    private function authorizeDraft(Request $request, InventoryCount $count): void
    {
        abort_unless($request->user()->hasPermission('inventory.counts.create'), 403);
        $this->ensureDraftOwner($count, $request->user()->id);
        abort_unless($request->user()->canAccessWarehouse($count->warehouse_id), 403);
    }

    private function authorizeView(Request $request, InventoryCount $count): void
    {
        abort_unless(
            $request->user()->hasPermission('inventory.counts.view')
                || $count->created_by_user_id === $request->user()->id,
            403,
        );
        abort_unless(
            $count->warehouse->branch->company_id === $this->company()->id
                && $request->user()->canAccessWarehouse($count->warehouse_id),
            403,
        );
    }

    private function accessibleWarehouse(int $warehouseId, $user, int $companyId, bool $lock = false): Warehouse
    {
        $query = $user->accessibleWarehouses()
            ->whereKey($warehouseId)
            ->whereHas('branch', fn (Builder $branches) => $branches->where('company_id', $companyId));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function scopeWarehouses(Builder $query, $user): void
    {
        if (! $user->hasGlobalWarehouseAccess()) {
            $query->whereIn('warehouse_id', $user->accessibleWarehouses()->select('warehouses.id'));
        }
    }

    private function stockBalanceMilli(int $warehouseId, int $productId, bool $lock): int
    {
        $lots = WarehouseLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->with('movements')
            ->get();

        return $lots->sum(function (WarehouseLot $lot) use ($lock) {
            $movements = $lock
                ? $lot->movements()->lockForUpdate()->get(['movement_type', 'quantity'])
                : $lot->movements;

            return max(0, $movements->sum(fn (StockMovement $movement) => $movement->movement_type === StockMovement::TYPE_RECEIPT
                ? $this->quantityMilli($movement->quantity)
                : -$this->quantityMilli($movement->quantity)));
        });
    }

    private function surplusUnitCost(int $warehouseId, int $productId): ?string
    {
        $lots = WarehouseLot::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->with('movements')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
        $totalQuantityMilli = 0;
        $totalValue = '0.0000';

        foreach ($lots as $lot) {
            $quantityMilli = $lot->movements->sum(fn (StockMovement $movement) => $movement->movement_type === StockMovement::TYPE_RECEIPT
                ? $this->quantityMilli($movement->quantity)
                : -$this->quantityMilli($movement->quantity));
            if ($quantityMilli <= 0) {
                continue;
            }

            $totalQuantityMilli += $quantityMilli;
            $lotValue = DecimalMath::multiply(
                (string) $lot->unit_cost_base,
                4,
                $this->quantityString($quantityMilli),
                3,
                4,
            );
            $totalValue = DecimalMath::add($totalValue, 4, $lotValue, 4, 4);
        }

        if ($totalQuantityMilli > 0) {
            return number_format(
                round((float) $totalValue / ($totalQuantityMilli / 1000), 4, PHP_ROUND_HALF_UP),
                4,
                '.',
                '',
            );
        }

        $latestLot = $lots->sortByDesc('received_at')->first();

        return $latestLot ? (string) $latestLot->unit_cost_base : null;
    }

    private function receiveSurplus(
        InventoryCount $count,
        Warehouse $warehouse,
        InventoryCountItem $item,
        int $quantityMilli,
        string $unitCost,
        ?string $expiresOn,
        int $userId,
        $occurredAt,
    ): void {
        $company = $this->company();
        $lot = WarehouseLot::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $item->product_id,
            'receipt_item_id' => null,
            'lot_code' => $count->reference.'-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT),
            'quantity_received' => $this->quantityString($quantityMilli),
            'unit_cost' => $unitCost,
            'unit_cost_base' => $unitCost,
            'quote_currency_id' => $company->base_currency_id,
            'base_currency_id' => $company->base_currency_id,
            'exchange_rate' => '1.00000000',
            'expires_on' => $expiresOn,
            'received_at' => $occurredAt,
        ]);
        StockMovement::query()->create([
            'warehouse_lot_id' => $lot->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $item->product_id,
            'user_id' => $userId,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'reference_type' => 'inventory_count',
            'reference_id' => $count->id,
            'quantity' => $this->quantityString($quantityMilli),
            'unit_cost_base' => $unitCost,
            'base_currency_id' => $company->base_currency_id,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function issueShortage(
        InventoryCount $count,
        Warehouse $warehouse,
        InventoryCountItem $item,
        int $quantityMilli,
        int $userId,
        $occurredAt,
    ): void {
        $lots = WarehouseLot::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $item->product_id)
            ->when(
                $item->product->tracks_expiration,
                fn (Builder $query) => $query->orderByRaw('CASE WHEN expires_on IS NULL THEN 0 ELSE 1 END')->orderBy('expires_on'),
            )
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $remainingMilli = $quantityMilli;

        foreach ($lots as $lot) {
            $balanceMilli = $lot->movements()->lockForUpdate()->get(['movement_type', 'quantity'])
                ->sum(fn (StockMovement $movement) => $movement->movement_type === StockMovement::TYPE_RECEIPT
                    ? $this->quantityMilli($movement->quantity)
                    : -$this->quantityMilli($movement->quantity));
            if ($balanceMilli <= 0) {
                continue;
            }

            $issuedMilli = min($remainingMilli, $balanceMilli);
            StockMovement::query()->create([
                'warehouse_lot_id' => $lot->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $item->product_id,
                'user_id' => $userId,
                'movement_type' => StockMovement::TYPE_ISSUE,
                'reference_type' => 'inventory_count',
                'reference_id' => $count->id,
                'quantity' => $this->quantityString($issuedMilli),
                'unit_cost_base' => $lot->unit_cost_base,
                'base_currency_id' => $lot->base_currency_id,
                'occurred_at' => $occurredAt,
            ]);
            $remainingMilli -= $issuedMilli;
            if ($remainingMilli === 0) {
                return;
            }
        }

        if ($remainingMilli > 0) {
            throw ValidationException::withMessages([
                'approval' => __('No fue posible asignar el faltante a los lotes actuales; devuelve el conteo al borrador y vuelve a contar.'),
            ]);
        }
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

    private function audit(Request $request, int $companyId, string $action, InventoryCount $count, array $changes): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'company_id' => $companyId,
            'action' => $action,
            'entity_type' => 'inventory_count',
            'entity_id' => $count->id,
            'changes' => $changes,
            'ip_address' => $request->ip(),
        ]);
    }
}
