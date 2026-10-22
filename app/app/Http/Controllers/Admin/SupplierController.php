<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['tax_id', 'legal_name', 'trade_name', 'contact_name', 'email', 'phone', 'address', 'is_active'];

    public function index(): View
    {
        $this->company();

        return view('admin.catalogs.suppliers.index', [
            'suppliers' => Supplier::query()->orderBy('legal_name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->company();

        return view('admin.catalogs.suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'tax_id' => ['nullable', 'string', 'max:50', 'unique:suppliers,tax_id'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['is_active'] = true;

        $supplier = DB::transaction(function () use ($request, $company, $validated) {
            $supplier = Supplier::create($validated);
            $this->audit($request, $company, 'created', 'supplier', $supplier, null, $supplier->only(self::AUDIT_FIELDS));

            return $supplier;
        });

        return redirect()->route('admin.catalogs.suppliers.edit', $supplier)->with('status', __('Proveedor creado.'));
    }

    public function edit(Supplier $supplier): View
    {
        $this->company();

        return view('admin.catalogs.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'tax_id' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'tax_id')->ignore($supplier->id)],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $company, $supplier, $validated) {
            $before = $supplier->only(self::AUDIT_FIELDS);
            $supplier->update($validated);
            $this->audit($request, $company, 'updated', 'supplier', $supplier, $before, $supplier->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.catalogs.suppliers.index')->with('status', __('Proveedor actualizado.'));
    }
}
