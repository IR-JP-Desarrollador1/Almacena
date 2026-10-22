<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->company = Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa de prueba',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
        ]);
        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
        $this->actingAs($this->administrator);
    }

    public function test_administrator_can_create_master_data_records_and_audit_them(): void
    {
        $this->post(route('admin.catalogs.units.store'), [
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
            'description' => 'Unidad básica',
        ])->assertRedirect();

        $unit = Unit::query()->where('code', 'UND')->firstOrFail();

        $this->post(route('admin.catalogs.products.store'), [
            'code' => 'PROD-01',
            'name' => 'Producto de prueba',
            'description' => 'Descripción',
            'unit_id' => $unit->id,
            'tracks_expiration' => '1',
        ])->assertRedirect();

        $product = Product::query()->where('code', 'PROD-01')->firstOrFail();
        $this->assertTrue($product->tracks_expiration);

        $this->post(route('admin.catalogs.suppliers.store'), [
            'tax_id' => '800123456',
            'legal_name' => 'Proveedor de prueba S.A.S.',
            'trade_name' => 'Proveedor',
            'contact_name' => 'Ana García',
            'email' => 'ventas@example.com',
            'phone' => '6011234567',
            'address' => 'Calle 2',
        ])->assertRedirect();

        $supplier = Supplier::query()->where('tax_id', '800123456')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'created',
            'entity_type' => 'unit',
            'entity_id' => $unit->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'product', 'entity_id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'supplier', 'entity_id' => $supplier->id]);
    }

    public function test_master_data_pages_render_for_administrators(): void
    {
        $this->get(route('admin.catalogs.products.index'))->assertOk();
        $this->get(route('admin.catalogs.products.create'))->assertOk();
        $this->get(route('admin.catalogs.units.index'))->assertOk();
        $this->get(route('admin.catalogs.units.create'))->assertOk();
        $this->get(route('admin.catalogs.suppliers.index'))->assertOk();
        $this->get(route('admin.catalogs.suppliers.create'))->assertOk();
    }

    public function test_catalog_view_permission_does_not_grant_write_access(): void
    {
        $viewer = User::factory()->create();
        $role = Role::create(['name' => 'Consulta de catálogos', 'slug' => 'consulta-catalogos']);
        $role->permissions()->attach(Permission::query()->where('code', 'catalogs.view')->firstOrFail());
        $viewer->roles()->attach($role);

        $this->actingAs($viewer)
            ->get(route('admin.catalogs.products.index'))
            ->assertOk();

        $this->post(route('admin.catalogs.units.store'), [
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ])->assertForbidden();
    }

    public function test_catalog_manage_permission_also_allows_read_access(): void
    {
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Gestión de catálogos', 'slug' => 'gestion-catalogos']);
        $role->permissions()->attach(Permission::query()->where('code', 'catalogs.manage')->firstOrFail());
        $manager->roles()->attach($role);

        $this->actingAs($manager)
            ->get(route('admin.catalogs.products.index'))
            ->assertOk();
    }

    public function test_units_in_use_cannot_be_deactivated_and_active_products_need_active_units(): void
    {
        $unit = Unit::create([
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ]);
        $product = Product::create([
            'code' => 'PROD-01',
            'name' => 'Producto',
            'unit_id' => $unit->id,
        ]);

        $this->put(route('admin.catalogs.units.update', $unit), [
            'code' => $unit->code,
            'name' => $unit->name,
            'abbreviation' => $unit->abbreviation,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($unit->fresh()->is_active);

        $unit->update(['is_active' => false]);
        $product->update(['is_active' => false]);

        $this->put(route('admin.catalogs.products.update', $product), [
            'code' => $product->code,
            'name' => $product->name,
            'unit_id' => $unit->id,
            'tracks_expiration' => '0',
            'is_active' => '1',
        ])->assertSessionHasErrors('unit_id');
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_unit_codes_abbreviations_and_supplier_tax_ids_must_be_unique(): void
    {
        Unit::create(['code' => 'UND', 'name' => 'Unidad', 'abbreviation' => 'und']);
        Supplier::create(['tax_id' => '800123456', 'legal_name' => 'Proveedor']);

        $this->post(route('admin.catalogs.units.store'), [
            'code' => 'UND',
            'name' => 'Otra unidad',
            'abbreviation' => 'otra',
        ])->assertSessionHasErrors('code');

        $this->post(route('admin.catalogs.units.store'), [
            'code' => 'OTRA',
            'name' => 'Otra unidad',
            'abbreviation' => 'und',
        ])->assertSessionHasErrors('abbreviation');

        $this->post(route('admin.catalogs.suppliers.store'), [
            'tax_id' => '800123456',
            'legal_name' => 'Otro proveedor',
        ])->assertSessionHasErrors('tax_id');
    }
}
