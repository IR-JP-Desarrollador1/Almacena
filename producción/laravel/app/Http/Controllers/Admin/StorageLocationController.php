<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\StorageLocation;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorageLocationController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['warehouse_id', 'code', 'name', 'description', 'is_active'];

    public function index(): View
    {
        $company = $this->company();

        return view('admin.organization.locations.index', [
            'locations' => StorageLocation::query()
                ->whereHas('warehouse.branch', fn ($query) => $query->where('company_id', $company->id))
                ->with('warehouse.branch')
                ->orderBy('code')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $company = $this->company();

        return view('admin.organization.locations.form', [
            'location' => new StorageLocation,
            'warehouses' => $this->availableWarehouses($company->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')
                ->where('is_active', true)
                ->whereIn('branch_id', Branch::query()->where('company_id', $company->id)->where('is_active', true)->select('id'))],
            'code' => ['required', 'string', 'max:50', Rule::unique('storage_locations', 'code')->where('warehouse_id', $request->input('warehouse_id'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->ensureWarehouseBelongsToCompany((int) $validated['warehouse_id'], $company->id);
        $validated['is_active'] = true;

        $location = DB::transaction(function () use ($request, $company, $validated) {
            $location = StorageLocation::create($validated);
            $this->audit($request, $company, 'created', 'storage_location', $location, null, $location->only(self::AUDIT_FIELDS));

            return $location;
        });

        return redirect()->route('admin.organization.locations.edit', $location)->with('status', __('Ubicación creada.'));
    }

    public function edit(StorageLocation $storageLocation): View
    {
        $company = $this->company();
        $location = $this->inCompany($storageLocation->id, $company->id);

        return view('admin.organization.locations.form', [
            'location' => $location,
            'warehouses' => $this->availableWarehouses($company->id, $location->warehouse_id),
        ]);
    }

    public function update(Request $request, StorageLocation $storageLocation): RedirectResponse
    {
        $company = $this->company();
        $location = $this->inCompany($storageLocation->id, $company->id);
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(
                fn ($query) => $query->where('is_active', true)->orWhere('id', $location->warehouse_id),
            )],
            'code' => ['required', 'string', 'max:50', Rule::unique('storage_locations', 'code')->where('warehouse_id', $request->input('warehouse_id'))->ignore($location->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $this->ensureWarehouseBelongsToCompany((int) $validated['warehouse_id'], $company->id);
        if ($validated['is_active'] && ! Warehouse::query()
            ->whereKey($validated['warehouse_id'])
            ->where('is_active', true)
            ->whereHas('branch', fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))
            ->exists()) {
            return back()->withErrors(['warehouse_id' => __('Una ubicación activa requiere una bodega y una sede activas.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $location, $validated) {
            $before = $location->only(self::AUDIT_FIELDS);
            $location->update($validated);
            $this->audit($request, $company, 'updated', 'storage_location', $location, $before, $location->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.organization.locations.index')->with('status', __('Ubicación actualizada.'));
    }

    private function availableWarehouses(int $companyId, ?int $currentId = null)
    {
        return Warehouse::query()
            ->whereHas('branch', fn ($query) => $query->where('company_id', $companyId)->where('is_active', true))
            ->where(fn ($query) => $query->where('is_active', true)->when($currentId, fn ($query) => $query->orWhere('id', $currentId)))
            ->with('branch')
            ->orderBy('name')
            ->get();
    }

    private function ensureWarehouseBelongsToCompany(int $warehouseId, int $companyId): void
    {
        abort_unless(
            Warehouse::query()->whereKey($warehouseId)->whereHas('branch', fn ($query) => $query->where('company_id', $companyId))->exists(),
            404,
        );
    }

    private function inCompany(int $id, int $companyId): StorageLocation
    {
        return StorageLocation::query()
            ->whereKey($id)
            ->whereHas('warehouse.branch', fn ($query) => $query->where('company_id', $companyId))
            ->firstOrFail();
    }
}
