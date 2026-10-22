<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RegionalFormatter;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegionalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
    }

    public function test_company_administrator_can_configure_and_apply_regional_preferences(): void
    {
        $company = $this->createCompany();

        $this->actingAs($this->administrator)
            ->get(route('admin.regional-settings.edit'))
            ->assertOk()
            ->assertSee('País de operación')
            ->assertSee('Português (Brasil)');

        $company->update(['language' => 'fr']);
        $this->get(route('admin.regional-settings.edit'))
            ->assertOk()
            ->assertSee('Paramètres régionaux')
            ->assertSee('États-Unis')
            ->assertSee('Virgule (,)');

        $this->put(route('admin.regional-settings.update'), [
            'regional_country_code' => 'BR',
            'language' => 'pt_BR',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
        ])->assertRedirect(route('admin.regional-settings.edit'));
        $this->assertSame('Configurações regionais salvas.', session('status'));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'regional_country_code' => 'BR',
            'language' => 'pt_BR',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'company_regional_settings',
            'entity_id' => $company->id,
            'action' => 'updated',
        ]);

        $this->get(route('dashboard'))
            ->assertSee('<html lang="pt-BR"', false)
            ->assertSee('MENU PRINCIPAL')
            ->assertSee('Dados mestres')
            ->assertSee('Painel principal');

        $formatter = RegionalFormatter::fromCompany($company->fresh());
        $this->assertSame('1.234,50', $formatter->number(1234.5));
        $this->assertSame('17/08/2026', $formatter->date('2026-08-17'));
    }

    public function test_regional_settings_require_a_supported_country_and_distinct_separators(): void
    {
        $this->createCompany();
        $this->actingAs($this->administrator)
            ->put(route('admin.regional-settings.update'), [
                'regional_country_code' => 'CA',
                'language' => 'es',
                'decimal_separator' => ',',
                'thousands_separator' => ',',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
            ])
            ->assertSessionHasErrors(['regional_country_code', 'thousands_separator']);
    }

    public function test_haiti_uses_a_non_breaking_thousands_separator_and_saves_successfully(): void
    {
        $company = $this->createCompany();
        $nonBreakingSpace = "\u{00A0}";

        $this->assertSame($nonBreakingSpace, config('regional.countries.HT.thousands'));
        $this->assertArrayHasKey($nonBreakingSpace, config('regional.thousands_separators'));

        $this->actingAs($this->administrator)
            ->put(route('admin.regional-settings.update'), [
                'regional_country_code' => 'HT',
                'language' => 'fr',
                'decimal_separator' => ',',
                'thousands_separator' => $nonBreakingSpace,
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
            ])
            ->assertRedirect(route('admin.regional-settings.edit'));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'regional_country_code' => 'HT',
            'language' => 'fr',
            'decimal_separator' => ',',
            'thousands_separator' => $nonBreakingSpace,
        ]);
        $this->assertSame('1 234 567,89', RegionalFormatter::fromCompany($company->fresh())->number(1234567.89));
    }

    public function test_validation_messages_and_regional_setting_attributes_are_translated(): void
    {
        $this->createCompany()->update(['language' => 'fr']);
        $this->actingAs($this->administrator)
            ->from(route('admin.regional-settings.edit'))
            ->put(route('admin.regional-settings.update'), [
                'regional_country_code' => 'HT',
                'language' => 'fr',
                'decimal_separator' => ',',
                'thousands_separator' => '',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
            ])
            ->assertSessionHasErrors([
                'thousands_separator' => 'Le champ séparateur de milliers est obligatoire.',
            ]);

        $this->from(route('admin.regional-settings.edit'))
            ->put(route('admin.regional-settings.update'), [
                'regional_country_code' => 'HT',
                'language' => 'fr',
                'decimal_separator' => ',',
                'thousands_separator' => ',',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
            ])
            ->assertSessionHasErrors([
                'thousands_separator' => 'Le champ séparateur de milliers doit être différent de séparateur décimal.',
            ]);
    }

    public function test_required_validation_messages_are_translated_for_all_supported_languages(): void
    {
        $messages = [
            'es' => 'El campo dato es obligatorio.',
            'en' => 'The dato field is required.',
            'fr' => 'Le champ dato est obligatoire.',
            'pt_BR' => 'O campo dato é obrigatório.',
        ];
        $originalLocale = app()->getLocale();

        try {
            foreach ($messages as $locale => $message) {
                app()->setLocale($locale);
                $validator = Validator::make([], ['field' => 'required'], [], ['field' => 'dato']);

                $this->assertSame($message, $validator->errors()->first('field'));
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_users_without_company_management_permission_cannot_open_regional_settings(): void
    {
        $this->createCompany();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.regional-settings.edit'))
            ->assertForbidden();
    }

    private function createCompany(): Company
    {
        return Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa regional',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
        ]);
    }
}
