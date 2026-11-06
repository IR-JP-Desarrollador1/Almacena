<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuditMaintenanceTest extends TestCase
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

    public function test_administrator_can_preview_and_delete_audit_logs_in_an_inclusive_date_range(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
        $insideStart = $this->createAuditLog('2026-11-01 00:00:00');
        $insideEnd = $this->createAuditLog('2026-11-02 23:59:59');
        $outside = $this->createAuditLog('2026-11-03 00:00:00');

        $this->actingAs($this->administrator)
            ->get(route('admin.audit.index', ['from_date' => '2026-11-01', 'to_date' => '2026-11-02']))
            ->assertOk()
            ->assertSee('Registros encontrados en el rango: 2')
            ->assertSee('Borrar registros del rango');

        $this->delete(route('admin.audit.maintenance.range'), [
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
        ])->assertSessionHasErrors('confirm_deletion');

        $this->assertDatabaseHas('audit_logs', ['id' => $insideStart->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $insideEnd->id]);

        $this->delete(route('admin.audit.maintenance.range'), [
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
            'confirm_deletion' => '1',
        ])->assertRedirect(route('admin.audit.index', [
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
        ]));

        $this->assertDatabaseMissing('audit_logs', ['id' => $insideStart->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $insideEnd->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $outside->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit_cleanup',
            'entity_type' => 'audit_logs',
        ]);
    }

    public function test_expired_cleanup_removes_only_records_older_than_30_days_and_keeps_its_audit_record(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
        $expired = $this->createAuditLog('2026-10-07 11:59:59');
        $exactlyThirtyDaysOld = $this->createAuditLog('2026-10-07 12:00:00');
        $recent = $this->createAuditLog('2026-11-06 11:59:59');

        $this->actingAs($this->administrator)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Registros que cumplen este criterio: 1');

        $this->delete(route('admin.audit.maintenance.expired'), [
            'confirm_deletion' => '1',
        ])->assertRedirect(route('admin.audit.index'));

        $this->assertDatabaseMissing('audit_logs', ['id' => $expired->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $exactlyThirtyDaysOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audit_cleanup',
            'entity_type' => 'audit_logs',
        ]);
    }

    public function test_read_only_audit_permission_cannot_manage_or_delete_audit_logs(): void
    {
        $viewer = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Consulta de auditoría',
            'slug' => 'consulta-auditoria',
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::query()->where('code', 'audit.view')->firstOrFail());
        $viewer->roles()->attach($role);

        $this->actingAs($viewer)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertDontSee('Mantenimiento de auditoría');

        $this->delete(route('admin.audit.maintenance.expired'), [
            'confirm_deletion' => '1',
        ])->assertForbidden();
    }

    public function test_invalid_or_reversed_date_ranges_are_rejected(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('admin.audit.index', [
                'from_date' => '2026-11-03',
                'to_date' => '2026-11-02',
            ]))
            ->assertSessionHasErrors('to_date');
    }

    private function createAuditLog(string $createdAt): AuditLog
    {
        return AuditLog::create([
            'user_id' => $this->administrator->id,
            'action' => 'test',
            'entity_type' => 'test',
            'created_at' => $createdAt,
        ]);
    }
}
