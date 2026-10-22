<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCatalogTest extends TestCase
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

    public function test_administrator_can_create_the_organizational_catalogs_and_audit_each_record(): void
    {
        $this->post(route('admin.organization.branches.store'), [
            'code' => 'BOG',
            'name' => 'Sede Bogotá',
            'address' => 'Calle 2',
            'phone' => '601123',
        ])->assertRedirect();

        $branch = Branch::query()->where('code', 'BOG')->firstOrFail();
        $this->post(route('admin.organization.warehouses.store'), [
            'branch_id' => $branch->id,
            'code' => 'BOD-01',
            'name' => 'Bodega principal',
            'address' => 'Calle 3',
        ])->assertRedirect();

        $warehouse = Warehouse::query()->where('code', 'BOD-01')->firstOrFail();
        $this->post(route('admin.organization.locations.store'), [
            'warehouse_id' => $warehouse->id,
            'code' => 'RACK-01',
            'name' => 'Estante 1',
            'description' => 'Zona seca',
        ])->assertRedirect();

        $this->post(route('admin.organization.cost-centers.store'), [
            'code' => 'CC-01',
            'name' => 'Operaciones',
            'description' => 'Gastos operativos',
        ])->assertRedirect();

        $this->assertDatabaseHas('storage_locations', ['warehouse_id' => $warehouse->id, 'code' => 'RACK-01']);
        $this->assertDatabaseHas('cost_centers', ['company_id' => $this->company->id, 'code' => 'CC-01']);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'created',
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'warehouse', 'entity_id' => $warehouse->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'storage_location']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'cost_center']);
    }

    public function test_catalog_pages_render_for_administrators(): void
    {
        $this->get(route('admin.organization.branches.index'))->assertOk();
        $this->get(route('admin.organization.branches.create'))->assertOk();
        $this->get(route('admin.organization.warehouses.index'))->assertOk();
        $this->get(route('admin.organization.warehouses.create'))->assertOk();
        $this->get(route('admin.organization.locations.index'))->assertOk();
        $this->get(route('admin.organization.locations.create'))->assertOk();
        $this->get(route('admin.organization.cost-centers.index'))->assertOk();
        $this->get(route('admin.organization.cost-centers.create'))->assertOk();
    }

    public function test_organization_view_permission_does_not_grant_write_access(): void
    {
        $viewer = User::factory()->create();
        $role = Role::create(['name' => 'Consulta organizacional', 'slug' => 'consulta-organizacional']);
        $role->permissions()->attach(Permission::query()->where('code', 'organization.view')->firstOrFail());
        $viewer->roles()->attach($role);

        $this->actingAs($viewer)
            ->get(route('admin.organization.branches.index'))
            ->assertOk();

        $this->post(route('admin.organization.branches.store'), [
            'code' => 'BOG',
            'name' => 'Sede Bogotá',
        ])->assertForbidden();
    }

    public function test_organization_manage_permission_also_allows_viewing_catalogs(): void
    {
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Gestión organizacional', 'slug' => 'gestion-organizacional']);
        $role->permissions()->attach(Permission::query()->where('code', 'organization.manage')->firstOrFail());
        $manager->roles()->attach($role);

        $this->actingAs($manager)
            ->get(route('admin.organization.branches.index'))
            ->assertOk();
    }

    public function test_codes_must_be_unique_within_their_parent_and_company(): void
    {
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'BOG',
            'name' => 'Sede Bogotá',
        ]);

        $this->post(route('admin.organization.branches.store'), [
            'code' => 'BOG',
            'name' => 'Sede duplicada',
        ])->assertSessionHasErrors('code');

        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'BOD-01',
            'name' => 'Bodega principal',
        ]);

        $this->post(route('admin.organization.warehouses.store'), [
            'branch_id' => $branch->id,
            'code' => 'BOD-01',
            'name' => 'Bodega duplicada',
        ])->assertSessionHasErrors('code');

        StorageLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'RACK-01',
            'name' => 'Estante 1',
        ]);

        $this->post(route('admin.organization.locations.store'), [
            'warehouse_id' => $warehouse->id,
            'code' => 'RACK-01',
            'name' => 'Estante duplicado',
        ])->assertSessionHasErrors('code');

        CostCenter::create([
            'company_id' => $this->company->id,
            'code' => 'CC-01',
            'name' => 'Operaciones',
        ]);

        $this->post(route('admin.organization.cost-centers.store'), [
            'code' => 'CC-01',
            'name' => 'Centro duplicado',
        ])->assertSessionHasErrors('code');
    }

    public function test_parent_cannot_be_deactivated_while_it_has_active_children(): void
    {
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'BOG',
            'name' => 'Sede Bogotá',
        ]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'BOD-01',
            'name' => 'Bodega principal',
        ]);
        StorageLocation::create([
            'warehouse_id' => $warehouse->id,
            'code' => 'RACK-01',
            'name' => 'Estante 1',
        ]);

        $this->put(route('admin.organization.warehouses.update', $warehouse), [
            'branch_id' => $branch->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($warehouse->fresh()->is_active);

        $this->put(route('admin.organization.branches.update', $branch), [
            'code' => $branch->code,
            'name' => $branch->name,
            'is_active' => '0',
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($branch->fresh()->is_active);
    }

    public function test_active_children_cannot_be_created_or_reactivated_under_inactive_parents(): void
    {
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'BOG',
            'name' => 'Sede Bogotá',
            'is_active' => false,
        ]);

        $this->post(route('admin.organization.warehouses.store'), [
            'branch_id' => $branch->id,
            'code' => 'BOD-01',
            'name' => 'Bodega principal',
        ])->assertSessionHasErrors('branch_id');

        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'BOD-02',
            'name' => 'Bodega inactiva',
            'is_active' => false,
        ]);

        $this->put(route('admin.organization.warehouses.update', $warehouse), [
            'branch_id' => $branch->id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'is_active' => '1',
        ])->assertSessionHasErrors('branch_id');

        $activeBranch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'MED',
            'name' => 'Sede Medellín',
        ]);
        $inactiveWarehouse = Warehouse::create([
            'branch_id' => $activeBranch->id,
            'code' => 'BOD-03',
            'name' => 'Bodega cerrada',
            'is_active' => false,
        ]);

        $this->post(route('admin.organization.locations.store'), [
            'warehouse_id' => $inactiveWarehouse->id,
            'code' => 'RACK-01',
            'name' => 'Estante 1',
        ])->assertSessionHasErrors('warehouse_id');

        $location = StorageLocation::create([
            'warehouse_id' => $inactiveWarehouse->id,
            'code' => 'RACK-02',
            'name' => 'Estante inactivo',
            'is_active' => false,
        ]);

        $this->put(route('admin.organization.locations.update', $location), [
            'warehouse_id' => $inactiveWarehouse->id,
            'code' => $location->code,
            'name' => $location->name,
            'is_active' => '1',
        ])->assertSessionHasErrors('warehouse_id');
    }
}
