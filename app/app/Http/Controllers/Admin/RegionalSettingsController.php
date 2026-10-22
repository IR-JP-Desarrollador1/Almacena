<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionalSettingsController extends Controller
{
    public function edit(): View
    {
        $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();

        return view('admin.regional-settings.edit', [
            'company' => $company,
            'countries' => config('regional.countries'),
            'languages' => config('regional.languages'),
            'decimalSeparators' => config('regional.decimal_separators'),
            'thousandsSeparators' => config('regional.thousands_separators'),
            'dateFormats' => config('regional.date_formats'),
            'timeFormats' => config('regional.time_formats'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'regional_country_code' => ['required', 'string', Rule::in(array_keys(config('regional.countries')))],
            'language' => ['required', 'string', Rule::in(array_keys(config('regional.languages')))],
            'decimal_separator' => ['required', 'string', Rule::in(array_keys(config('regional.decimal_separators')))],
            'thousands_separator' => ['required', 'string', Rule::in(array_keys(config('regional.thousands_separators'))), 'different:decimal_separator'],
            'date_format' => ['required', 'string', Rule::in(array_keys(config('regional.date_formats')))],
            'time_format' => ['required', 'string', Rule::in(array_keys(config('regional.time_formats')))],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $company = Company::query()
                ->where('singleton_key', 'primary')
                ->lockForUpdate()
                ->firstOrFail();
            $before = $company->only(array_keys($validated));
            $company->update($validated);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'company_id' => $company->id,
                'action' => 'updated',
                'entity_type' => 'company_regional_settings',
                'entity_id' => $company->id,
                'changes' => ['before' => $before, 'after' => $company->only(array_keys($validated))],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('admin.regional-settings.edit')->with('status', __('Configuración regional guardada.'));
    }
}
