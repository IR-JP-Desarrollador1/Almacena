<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['code', 'name', 'address', 'phone', 'is_active'];

    public function index(): View
    {
        $company = $this->company();

        return view('admin.organization.branches.index', [
            'branches' => $company->branches()->withCount('warehouses')->orderBy('code')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->company();

        return view('admin.organization.branches.form', ['branch' => new Branch]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->where('company_id', $company->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $validated['is_active'] = true;

        $branch = DB::transaction(function () use ($request, $company, $validated) {
            $branch = $company->branches()->create($validated);
            $this->audit($request, $company, 'created', 'branch', $branch, null, $branch->only(self::AUDIT_FIELDS));

            return $branch;
        });

        return redirect()->route('admin.organization.branches.edit', $branch)->with('status', __('Sede creada.'));
    }

    public function edit(Branch $branch): View
    {
        $company = $this->company();
        $branch = $company->branches()->findOrFail($branch->id);

        return view('admin.organization.branches.form', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $company = $this->company();
        $branch = $company->branches()->findOrFail($branch->id);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->where('company_id', $company->id)->ignore($branch->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && $branch->warehouses()->where('is_active', true)->exists()) {
            return back()->withErrors(['is_active' => __('Desactive primero las bodegas activas de esta sede.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $branch, $validated) {
            $before = $branch->only(self::AUDIT_FIELDS);
            $branch->update($validated);
            $this->audit($request, $company, 'updated', 'branch', $branch, $before, $branch->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.organization.branches.index')->with('status', __('Sede actualizada.'));
    }
}
