<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function edit(): View
    {
        $company = Company::query()->where('singleton_key', 'primary')->first() ?? new Company;

        return view('admin.company.edit', [
            'company' => $company,
            'currencies' => Currency::query()
                ->where(fn ($query) => $query->where('is_active', true)->when($company->base_currency_id, fn ($query) => $query->orWhere('id', $company->base_currency_id)))
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = Company::query()->where('singleton_key', 'primary')->first();
        $validated = $request->validate([
            'tax_id' => ['required', 'string', 'max:50', Rule::unique('companies', 'tax_id')->ignore($company?->id)],
            'legal_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'legal_representative_name' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'administrative_division' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:150'],
            'base_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
        ]);

        DB::transaction(function () use ($request, $company, $validated) {
            if ($company) {
                $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
                if (
                    (int) $company->base_currency_id !== (int) $validated['base_currency_id']
                    && (
                        SupplierQuote::query()->exists()
                        || PurchaseOrder::query()
                            ->whereHas('requisition.department', fn ($query) => $query->where('company_id', $company->id))
                            ->exists()
                    )
                ) {
                    throw ValidationException::withMessages([
                        'base_currency_id' => __('La moneda base no se puede cambiar después de registrar cotizaciones u órdenes de compra.'),
                    ]);
                }
                $before = $company->only(array_keys($validated));
                $company->update($validated);
                $action = 'updated';
            } else {
                $company = Company::create([
                    ...$validated,
                    ...$this->regionalSettingsForCountry($validated['country']),
                ]);
                $before = null;
                $action = 'created';
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => $action,
                'entity_type' => 'company',
                'entity_id' => $company->id,
                'changes' => ['before' => $before, 'after' => $company->only(array_keys($validated))],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('admin.company.edit')->with('status', __('Datos de la empresa guardados.'));
    }

    private function regionalSettingsForCountry(string $country): array
    {
        $normalizedCountry = mb_strtolower(Str::ascii(trim($country)));
        $countryCode = collect(config('regional.countries'))
            ->search(fn (array $preset) => mb_strtolower(Str::ascii($preset['name'])) === $normalizedCountry);
        $countryCode = $countryCode === false ? 'CO' : $countryCode;
        $preset = config("regional.countries.{$countryCode}");

        return [
            'regional_country_code' => $countryCode,
            'language' => $preset['language'],
            'decimal_separator' => $preset['decimal'],
            'thousands_separator' => $preset['thousands'],
            'date_format' => $preset['date'],
            'time_format' => $preset['time'],
        ];
    }
}
