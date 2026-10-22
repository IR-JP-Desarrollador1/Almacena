<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdministrationTest extends TestCase
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

    public function test_administrator_can_create_a_pending_user_and_send_activation_link(): void
    {
        Notification::fake();

        $this->actingAs($this->administrator)->post(route('admin.users.store'), [
            'name' => 'Ana',
            'last_name' => 'García',
            'email' => 'ana@example.com',
            'phone' => '3001234567',
            'roles' => [Role::query()->where('slug', 'compras')->value('id')],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['compras'], $user->roles()->pluck('slug')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_administrator_can_assign_multiple_warehouses_to_a_user_and_revoke_sessions_when_scope_changes(): void
    {
        Notification::fake();
        $company = Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa de prueba',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'MAIN',
            'name' => 'Bodega principal',
        ]);
        $otherWarehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'SECOND',
            'name' => 'Bodega secundaria',
        ]);

        $this->actingAs($this->administrator)->post(route('admin.users.store'), [
            'name' => 'Operador',
            'last_name' => 'Bodega',
            'email' => 'operador@example.com',
            'roles' => [Role::query()->where('slug', 'almacen')->value('id')],
            'warehouse_assignments_submitted' => '1',
            'warehouses' => [$warehouse->id, $otherWarehouse->id],
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'operador@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$warehouse->id, $otherWarehouse->id],
            $user->warehouses()->pluck('warehouses.id')->all(),
        );
        $this->actingAs($this->administrator)
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Bodega principal')
            ->assertSee('Bodega secundaria');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);

        $user->update(['status' => User::STATUS_ACTIVE]);
        $this->actingAs($this->administrator)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'status' => User::STATUS_ACTIVE,
            'roles' => $user->roles()->pluck('roles.id')->all(),
            'warehouse_assignments_submitted' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(0, $user->fresh()->warehouses()->count());
        $this->assertSame(1, $user->fresh()->session_version);
        $audit = AuditLog::query()
            ->where('entity_type', 'user')
            ->where('entity_id', $user->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue($audit->changes['warehouses_changed']);
    }

    public function test_administrator_has_global_warehouse_access_without_explicit_assignment(): void
    {
        $company = Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa de prueba',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'MAIN',
            'name' => 'Bodega principal',
        ]);

        $this->assertTrue($this->administrator->canAccessWarehouse($warehouse->id));
        $this->assertTrue($this->administrator->accessibleWarehouses()->whereKey($warehouse->id)->exists());
    }

    public function test_user_cannot_be_assigned_to_an_inactive_warehouse(): void
    {
        $company = Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa de prueba',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'INACTIVE',
            'name' => 'Bodega inactiva',
            'is_active' => false,
        ]);

        $this->actingAs($this->administrator)->post(route('admin.users.store'), [
            'name' => 'Operador',
            'last_name' => 'Inactivo',
            'email' => 'inactivo@example.com',
            'roles' => [Role::query()->where('slug', 'almacen')->value('id')],
            'warehouse_assignments_submitted' => '1',
            'warehouses' => [$warehouse->id],
        ])->assertSessionHasErrors('warehouses');

        $this->assertDatabaseMissing('users', ['email' => 'inactivo@example.com']);
        $this->assertFalse($this->administrator->canAccessWarehouse($warehouse->id));
    }

    public function test_pending_user_activates_account_after_setting_password_from_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'status' => User::STATUS_PENDING,
            'must_change_password' => true,
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertNotNull($notification);

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Inventarios2026Seguro!',
            'password_confirmation' => 'Inventarios2026Seguro!',
        ])->assertRedirect(route('login'));

        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation_accepted',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_administrator_cannot_deactivate_the_last_administrator(): void
    {
        $this->actingAs($this->administrator)->put(route('admin.users.update', $this->administrator), [
            'name' => $this->administrator->name,
            'last_name' => $this->administrator->last_name,
            'email' => $this->administrator->email,
            'phone' => $this->administrator->phone,
            'status' => User::STATUS_INACTIVE,
            'roles' => $this->administrator->roles()->pluck('roles.id')->all(),
        ])->assertSessionHasErrors('status');

        $this->assertSame(User::STATUS_ACTIVE, $this->administrator->fresh()->status);
    }

    public function test_role_change_revokes_existing_user_sessions(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());

        $this->actingAs($this->administrator)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => User::STATUS_ACTIVE,
            'roles' => [Role::query()->where('slug', 'logistica')->value('id')],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertSame(['logistica'], $user->fresh()->roles()->pluck('slug')->all());
    }

    public function test_administrator_can_change_a_users_password_and_revoke_existing_sessions(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword2026!']);
        $user->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());
        $newPassword = 'NewPassword2026!';

        $this->actingAs($this->administrator)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => User::STATUS_ACTIVE,
            'roles' => $user->roles()->pluck('roles.id')->all(),
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check('OldPassword2026!', $user->password));
        $this->assertSame(1, $user->session_version);

        $audit = AuditLog::query()
            ->where('entity_type', 'user')
            ->where('entity_id', $user->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue($audit->changes['password_changed']);
        $this->assertStringNotContainsString($newPassword, json_encode($audit->changes));
    }

    public function test_blank_password_does_not_change_users_password(): void
    {
        $user = User::factory()->create(['password' => 'OriginalPassword2026!']);
        $user->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());
        $originalHash = $user->password;

        $this->actingAs($this->administrator)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => User::STATUS_ACTIVE,
            'roles' => $user->roles()->pluck('roles.id')->all(),
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertSame(0, $user->fresh()->session_version);
    }

    public function test_password_change_requires_confirmation(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());

        $this->actingAs($this->administrator)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => User::STATUS_ACTIVE,
            'roles' => $user->roles()->pluck('roles.id')->all(),
            'password' => 'NewPassword2026!',
            'password_confirmation' => 'DifferentPassword2026!',
        ])->assertSessionHasErrors('password');
    }

    public function test_user_without_permission_cannot_open_administration_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.company.edit'))->assertForbidden();
    }

    public function test_administrator_pages_render(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('admin.users.index'))
            ->assertOk();

        $user = User::factory()->create();

        $this->get(route('admin.users.create'))->assertOk();
        $this->get(route('admin.users.edit', $user))->assertOk();
        $this->get(route('admin.roles.index'))->assertOk();
        $this->get(route('admin.roles.create'))->assertOk();
        $this->get(route('admin.roles.edit', Role::query()->where('slug', 'administracion')->firstOrFail()))->assertOk();
        $this->get(route('admin.company.edit'))->assertOk();
        $this->get(route('admin.catalogs.currencies.index'))->assertOk();
        $this->get(route('admin.audit.index'))->assertOk();
    }

    public function test_administrator_can_manage_currencies_and_cannot_deactivate_a_currency_in_use(): void
    {
        $this->assertDatabaseHas('currencies', [
            'code' => 'CNY',
            'name' => 'Yuan renminbi chino',
            'symbol' => '¥',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('currencies', [
            'code' => 'RUB',
            'name' => 'Rublo ruso',
            'symbol' => '₽',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator)
            ->post(route('admin.catalogs.currencies.store'), [
                'code' => 'JPY',
                'name' => 'Yen japonés',
                'symbol' => '¥',
                'decimal_places' => 0,
            ])
            ->assertRedirect();

        $yen = Currency::query()->where('code', 'JPY')->firstOrFail();
        $this->actingAs($this->administrator)
            ->put(route('admin.catalogs.currencies.update', $yen), [
                'name' => 'Yen japonés',
                'symbol' => '¥',
                'decimal_places' => 0,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.catalogs.currencies.index'));
        $this->assertDatabaseHas('currencies', ['id' => $yen->id, 'is_active' => false]);

        $cop = Currency::query()->where('code', 'COP')->firstOrFail();
        Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa con moneda base',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
            'base_currency_id' => $cop->id,
        ]);
        $this->actingAs($this->administrator)
            ->put(route('admin.catalogs.currencies.update', $cop), [
                'name' => $cop->name,
                'symbol' => $cop->symbol,
                'decimal_places' => $cop->decimal_places,
                'is_active' => 0,
            ])
            ->assertSessionHasErrors('is_active');
        $this->assertTrue($cop->fresh()->is_active);
    }

    public function test_administrator_can_save_single_company_profile_and_audit_it(): void
    {
        $copId = Currency::query()->where('code', 'COP')->value('id');
        $this->actingAs($this->administrator)->put(route('admin.company.update'), [
            'tax_id' => '900123456',
            'legal_name' => 'Inventarios Demo S.A.S.',
            'address' => 'Calle 1 # 2-3',
            'phone' => '6011234567',
            'email' => 'contacto@example.com',
            'legal_representative_name' => 'Ana García',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
            'base_currency_id' => $copId,
        ])->assertRedirect(route('admin.company.edit'));

        $this->assertDatabaseHas('companies', [
            'tax_id' => '900123456',
            'legal_name' => 'Inventarios Demo S.A.S.',
            'singleton_key' => 'primary',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => 'company',
        ]);

        $this->actingAs($this->administrator)->put(route('admin.company.update'), [
            'tax_id' => '900123456',
            'legal_name' => 'Inventarios Demo S.A.S. Actualizada',
            'address' => 'Calle 1 # 2-3',
            'phone' => '6011234567',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
            'base_currency_id' => $copId,
        ])->assertRedirect(route('admin.company.edit'));

        $this->assertSame(1, Company::query()->count());
        $this->assertSame(2, AuditLog::query()->where('entity_type', 'company')->count());
    }

    public function test_company_profile_updates_the_single_company_and_allows_its_own_tax_id(): void
    {
        Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa existente',
            'address' => 'Dirección',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Antioquia',
            'city' => 'Medellín',
            'base_currency_id' => Currency::query()->where('code', 'COP')->value('id'),
        ]);

        $this->actingAs($this->administrator)->put(route('admin.company.update'), [
            'tax_id' => '900123456',
            'legal_name' => 'Empresa existente actualizada',
            'address' => 'Dirección',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Antioquia',
            'city' => 'Medellín',
            'base_currency_id' => Currency::query()->where('code', 'COP')->value('id'),
        ])->assertRedirect(route('admin.company.edit'));

        $this->assertSame(1, Company::query()->count());
        $this->assertDatabaseHas('companies', ['legal_name' => 'Empresa existente actualizada']);
    }

    public function test_database_prevents_a_second_company_record(): void
    {
        Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa existente',
            'address' => 'Dirección',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Antioquia',
            'city' => 'Medellín',
        ]);

        $this->expectException(QueryException::class);

        Company::create([
            'tax_id' => '900654321',
            'legal_name' => 'Segunda empresa',
            'address' => 'Dirección',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Antioquia',
            'city' => 'Medellín',
        ]);
    }

    public function test_administrator_can_create_custom_role_with_permissions(): void
    {
        $permission = Permission::query()->where('code', 'users.view')->firstOrFail();

        $this->actingAs($this->administrator)->post(route('admin.roles.store'), [
            'name' => 'Consulta de usuarios',
            'description' => 'Solo lectura',
            'permissions' => [$permission->id],
        ])->assertRedirect();

        $role = Role::query()->where('slug', 'consulta-de-usuarios')->firstOrFail();
        $this->assertSame([$permission->id], $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => 'role',
            'entity_id' => $role->id,
        ]);
    }

    public function test_administrator_profile_cannot_lose_core_administration_permissions(): void
    {
        $role = Role::query()->where('slug', 'administracion')->firstOrFail();

        $this->actingAs($this->administrator)->put(route('admin.roles.update', $role), [
            'name' => $role->name,
            'description' => $role->description,
            'is_active' => '1',
            'permissions' => [
                Permission::query()->where('code', 'users.manage')->value('id'),
            ],
        ])->assertSessionHasErrors('permissions');

        $this->assertTrue($role->fresh()->permissions()->where('code', 'roles.manage')->exists());
    }
}
