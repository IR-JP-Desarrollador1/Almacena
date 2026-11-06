<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseUpdateTest extends TestCase
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

    public function test_administrator_can_view_database_updates_from_the_dashboard(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('admin.database-update.index'))
            ->assertOk()
            ->assertSee('Migraciones pendientes')
            ->assertSee('No hay migraciones pendientes para aplicar.');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Actualizar base de datos')
            ->assertSee(route('admin.database-update.index'));
    }

    public function test_user_without_database_migration_permission_cannot_view_or_run_updates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.database-update.index'))
            ->assertForbidden();

        $this->post(route('admin.database-update.run'), ['confirm_update' => '1'])
            ->assertForbidden();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Actualizar base de datos');
    }

    public function test_migrations_require_confirmation_and_successful_runs_are_audited(): void
    {
        $migration = '2026_11_06_000000_add_database_migration_permission';

        $this->actingAs($this->administrator)
            ->from(route('admin.database-update.index'))
            ->post(route('admin.database-update.run'))
            ->assertRedirect(route('admin.database-update.index'))
            ->assertSessionHasErrors('confirm_update');

        $this->assertSame(1, DB::table('migrations')->where('migration', $migration)->delete());

        $this->post(route('admin.database-update.run'), ['confirm_update' => '1'])
            ->assertRedirect(route('admin.database-update.index'));

        $this->assertSame('La base de datos se actualizó correctamente.', session('status'));
        $this->assertDatabaseHas('migrations', ['migration' => $migration]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->administrator->id,
            'action' => 'migrations_applied',
            'entity_type' => 'database',
        ]);
    }
}
