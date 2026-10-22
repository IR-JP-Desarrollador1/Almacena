<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RegionalFormatter;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
