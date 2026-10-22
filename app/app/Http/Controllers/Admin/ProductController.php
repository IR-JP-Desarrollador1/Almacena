<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['code', 'name', 'description', 'unit_id', 'tracks_expiration', 'is_active'];

    public function index(): View
    {
        $this->company();

        return view('admin.catalogs.products.index', [
            'products' => Product::query()->with('unit')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->company();

        return view('admin.catalogs.products.form', [
            'product' => new Product,
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('is_active', true)],
            'tracks_expiration' => ['required', 'boolean'],
        ]);
        $validated['is_active'] = true;

        $product = DB::transaction(function () use ($request, $company, $validated) {
            $product = Product::create($validated);
            $this->audit($request, $company, 'created', 'product', $product, null, $product->only(self::AUDIT_FIELDS));

            return $product;
        });

        return redirect()->route('admin.catalogs.products.edit', $product)->with('status', __('Producto creado.'));
    }

    public function edit(Product $product): View
    {
        $this->company();

        return view('admin.catalogs.products.form', [
            'product' => $product->load('unit'),
            'units' => Unit::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $product->unit_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where(
                fn ($query) => $query->where('is_active', true)->orWhere('id', $product->unit_id),
            )],
            'tracks_expiration' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validated['is_active'] && ! Unit::query()->whereKey($validated['unit_id'])->where('is_active', true)->exists()) {
            return back()->withErrors(['unit_id' => __('Un producto activo requiere una unidad activa.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $product, $validated) {
            $before = $product->only(self::AUDIT_FIELDS);
            $product->update($validated);
            $this->audit($request, $company, 'updated', 'product', $product, $before, $product->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.catalogs.products.index')->with('status', __('Producto actualizado.'));
    }
}
