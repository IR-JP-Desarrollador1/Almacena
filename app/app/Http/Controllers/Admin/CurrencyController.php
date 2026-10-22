<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    private const AUDIT_FIELDS = ['code', 'name', 'symbol', 'decimal_places', 'is_active'];

    public function index(): View
    {
        return view('admin.catalogs.currencies.index', [
            'currencies' => Currency::query()
                ->withCount(['companiesUsingAsBase', 'purchaseOrdersUsingAsQuote', 'purchaseOrdersUsingAsBase'])
                ->orderBy('code')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.catalogs.currencies.form', ['currency' => new Currency]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'uppercase', 'regex:/^[A-Z]{3}$/', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
        ]);
        $validated['is_active'] = true;
        $currency = DB::transaction(function () use ($validated, $request) {
            $currency = Currency::create($validated);
            $this->audit($request, 'created', $currency, null, $currency->only(self::AUDIT_FIELDS));

            return $currency;
        });

        return redirect()->route('admin.catalogs.currencies.edit', $currency)->with('status', __('Moneda creada.'));
    }

    public function edit(Currency $currency): View
    {
        return view('admin.catalogs.currencies.form', compact('currency'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $validated['is_active'] && $this->isInUse($currency)) {
            throw ValidationException::withMessages([
                'is_active' => __('No se puede desactivar una moneda usada como moneda base de la empresa o en órdenes de compra.'),
            ]);
        }

        DB::transaction(function () use ($request, $currency, $validated) {
            $before = $currency->only(self::AUDIT_FIELDS);
            $currency->update($validated);
            $this->audit($request, 'updated', $currency, $before, $currency->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.catalogs.currencies.index')->with('status', __('Moneda actualizada.'));
    }

    private function isInUse(Currency $currency): bool
    {
        return $currency->companiesUsingAsBase()->exists()
            || PurchaseOrder::query()
                ->where('quote_currency_id', $currency->id)
                ->orWhere('base_currency_id', $currency->id)
                ->exists();
    }

    private function audit(Request $request, string $action, Currency $currency, ?array $before, array $after): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'company_id' => Company::query()->where('singleton_key', 'primary')->value('id'),
            'action' => $action,
            'entity_type' => 'currency',
            'entity_id' => $currency->id,
            'changes' => ['before' => $before, 'after' => $after],
            'ip_address' => $request->ip(),
        ]);
    }
}
