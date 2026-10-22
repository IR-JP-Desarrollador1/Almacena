<?php

namespace App\Http\Controllers\Admin;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['code', 'name', 'abbreviation', 'description', 'is_active'];

    public function index(): View
    {
        $this->company();

        return view('admin.catalogs.units.index', [
            'units' => Unit::query()->withCount('products')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->company();

        return view('admin.catalogs.units.form', ['unit' => new Unit]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:units,code'],
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20', 'unique:units,abbreviation'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['is_active'] = true;

        $unit = DB::transaction(function () use ($request, $company, $validated) {
            $unit = Unit::create($validated);
            $this->audit($request, $company, 'created', 'unit', $unit, null, $unit->only(self::AUDIT_FIELDS));

            return $unit;
        });

        return redirect()->route('admin.catalogs.units.edit', $unit)->with('status', __('Unidad creada.'));
    }

    public function edit(Unit $unit): View
    {
        $this->company();

        return view('admin.catalogs.units.form', compact('unit'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('units', 'code')->ignore($unit->id)],
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('units', 'abbreviation')->ignore($unit->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && $unit->products()->where('is_active', true)->exists()) {
            return back()->withErrors(['is_active' => __('No se puede desactivar una unidad que tiene productos activos.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $unit, $validated) {
            $before = $unit->only(self::AUDIT_FIELDS);
            $unit->update($validated);
            $this->audit($request, $company, 'updated', 'unit', $unit, $before, $unit->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.catalogs.units.index')->with('status', __('Unidad actualizada.'));
    }
}
