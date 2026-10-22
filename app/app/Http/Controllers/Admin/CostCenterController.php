<?php

namespace App\Http\Controllers\Admin;

use App\Models\CostCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CostCenterController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['code', 'name', 'description', 'is_active'];

    public function index(): View
    {
        $company = $this->company();

        return view('admin.organization.cost-centers.index', [
            'costCenters' => $company->costCenters()->orderBy('code')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->company();

        return view('admin.organization.cost-centers.form', ['costCenter' => new CostCenter]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('cost_centers', 'code')->where('company_id', $company->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['is_active'] = true;

        $costCenter = DB::transaction(function () use ($request, $company, $validated) {
            $costCenter = $company->costCenters()->create($validated);
            $this->audit($request, $company, 'created', 'cost_center', $costCenter, null, $costCenter->only(self::AUDIT_FIELDS));

            return $costCenter;
        });

        return redirect()->route('admin.organization.cost-centers.edit', $costCenter)->with('status', __('Centro de costo creado.'));
    }

    public function edit(CostCenter $costCenter): View
    {
        $company = $this->company();
        $costCenter = $company->costCenters()->findOrFail($costCenter->id);

        return view('admin.organization.cost-centers.form', compact('costCenter'));
    }

    public function update(Request $request, CostCenter $costCenter): RedirectResponse
    {
        $company = $this->company();
        $costCenter = $company->costCenters()->findOrFail($costCenter->id);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('cost_centers', 'code')->where('company_id', $company->id)->ignore($costCenter->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $company, $costCenter, $validated) {
            $before = $costCenter->only(self::AUDIT_FIELDS);
            $costCenter->update($validated);
            $this->audit($request, $company, 'updated', 'cost_center', $costCenter, $before, $costCenter->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.organization.cost-centers.index')->with('status', __('Centro de costo actualizado.'));
    }
}
