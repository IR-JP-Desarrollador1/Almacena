<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['branch_id', 'code', 'name', 'address', 'is_active'];

    public function index(): View
    {
        $company = $this->company();

        return view('admin.organization.warehouses.index', [
            'warehouses' => Warehouse::query()
                ->whereHas('branch', fn ($query) => $query->where('company_id', $company->id))
                ->with('branch')
                ->withCount('storageLocations')
                ->orderBy('code')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $company = $this->company();

        return view('admin.organization.warehouses.form', [
            'warehouse' => new Warehouse,
            'branches' => $company->branches()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $company->id)->where('is_active', true)],
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->where('branch_id', $request->input('branch_id'))],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['is_active'] = true;

        $warehouse = DB::transaction(function () use ($request, $company, $validated) {
            $warehouse = Warehouse::create($validated);
            $this->audit($request, $company, 'created', 'warehouse', $warehouse, null, $warehouse->only(self::AUDIT_FIELDS));

            return $warehouse;
        });

        return redirect()->route('admin.organization.warehouses.edit', $warehouse)->with('status', __('Bodega creada.'));
    }

    public function edit(Warehouse $warehouse): View
    {
        $company = $this->company();
        $warehouse = $this->inCompany($warehouse->id, $company->id);

        return view('admin.organization.warehouses.form', [
            'warehouse' => $warehouse,
            'branches' => $company->branches()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $warehouse->branch_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $company = $this->company();
        $warehouse = $this->inCompany($warehouse->id, $company->id);
        $validated = $request->validate([
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where('company_id', $company->id)->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $warehouse->branch_id),
                ),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->where('branch_id', $request->input('branch_id'))->ignore($warehouse->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validated['is_active'] && ! Branch::query()
            ->whereKey($validated['branch_id'])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->exists()) {
            return back()->withErrors(['branch_id' => __('No se puede activar una bodega dentro de una sede inactiva.')])->withInput();
        }

        if (! $validated['is_active'] && $warehouse->storageLocations()->where('is_active', true)->exists()) {
            return back()->withErrors(['is_active' => __('Desactive primero las ubicaciones activas de esta bodega.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $warehouse, $validated) {
            $before = $warehouse->only(self::AUDIT_FIELDS);
            $warehouse->update($validated);
            $this->audit($request, $company, 'updated', 'warehouse', $warehouse, $before, $warehouse->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.organization.warehouses.index')->with('status', __('Bodega actualizada.'));
    }

    private function inCompany(int $id, int $companyId): Warehouse
    {
        return Warehouse::query()
            ->whereKey($id)
            ->whereHas('branch', fn ($query) => $query->where('company_id', $companyId))
            ->firstOrFail();
    }
}
