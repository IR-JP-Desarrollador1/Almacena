<?php

namespace Tests\Feature\Purchasing;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $requester;

    private Department $department;

    private Product $product;

    private Warehouse $warehouse;

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
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $this->warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'MAIN',
            'name' => 'Bodega principal',
        ]);
        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());
        $this->manager->warehouses()->attach($this->warehouse);
        $this->requester = User::factory()->create();
        $this->requester->roles()->attach(Role::query()->where('slug', 'solicitante')->firstOrFail());
        $this->requester->warehouses()->attach($this->warehouse);
        $this->department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operaciones',
            'manager_user_id' => $this->manager->id,
        ]);
        $this->requester->update(['department_id' => $this->department->id]);
        $unit = Unit::create([
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ]);
        $this->product = Product::create([
            'code' => 'PROD-01',
            'name' => 'Producto de prueba',
            'unit_id' => $unit->id,
        ]);
    }

    public function test_requester_creates_submits_and_only_the_assigned_manager_approves(): void
    {
        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.store'), $this->requisitionData())
            ->assertRedirect();

        $requisition = Requisition::query()->firstOrFail();
        $this->assertSame('REQ-'.str_pad((string) $requisition->id, 8, '0', STR_PAD_LEFT), $requisition->reference);
        $this->assertLessThanOrEqual(30, strlen($requisition->reference));
        $this->assertSame($this->department->id, $requisition->department_id);
        $this->assertSame($this->requester->id, $requisition->requester_user_id);
        $this->assertDatabaseHas('requisition_items', [
            'requisition_id' => $requisition->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'unit_abbreviation' => 'und',
            'quantity' => 2.5,
        ]);

        $this->post(route('purchasing.requisitions.submit', $requisition))
            ->assertRedirect(route('purchasing.requisitions.show', $requisition));
        $requisition->refresh();
        $this->assertSame(Requisition::STATUS_PENDING_APPROVAL, $requisition->status);
        $this->assertSame($this->manager->id, $requisition->approver_user_id);

        $otherManager = User::factory()->create();
        $otherManager->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());
        $this->actingAs($otherManager)
            ->post(route('purchasing.requisitions.approve', $requisition))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('purchasing.requisitions.approve', $requisition))
            ->assertRedirect(route('purchasing.requisitions.show', $requisition));

        $this->assertSame(Requisition::STATUS_APPROVED, $requisition->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'approved',
            'entity_type' => 'requisition',
            'entity_id' => $requisition->id,
        ]);
        $auditChanges = AuditLog::query()
            ->where('entity_type', 'requisition')
            ->where('entity_id', $requisition->id)
            ->where('action', 'created')
            ->value('changes');
        $this->assertSame('2.500', $auditChanges['after']['items'][0]['quantity']);
    }

    public function test_manager_can_reject_with_reason_and_requester_can_correct_and_resubmit(): void
    {
        $this->actingAs($this->requester)->post(route('purchasing.requisitions.store'), $this->requisitionData());
        $requisition = Requisition::query()->firstOrFail();
        $this->post(route('purchasing.requisitions.submit', $requisition));

        $this->actingAs($this->manager)
            ->post(route('purchasing.requisitions.reject', $requisition), ['rejection_reason' => 'Ajustar cantidad solicitada'])
            ->assertRedirect(route('purchasing.requisitions.show', $requisition));
        $this->assertSame(Requisition::STATUS_REJECTED, $requisition->fresh()->status);

        $corrected = $this->requisitionData();
        $corrected['items'][0]['quantity'] = '1.000';
        $this->actingAs($this->requester)
            ->put(route('purchasing.requisitions.update', $requisition), $corrected)
            ->assertRedirect(route('purchasing.requisitions.show', $requisition));

        $this->assertSame(Requisition::STATUS_DRAFT, $requisition->fresh()->status);
        $this->assertNull($requisition->fresh()->rejection_reason);
        $this->assertSame('1.000', $requisition->items()->firstOrFail()->quantity);
        $this->post(route('purchasing.requisitions.submit', $requisition));
        $this->assertSame(Requisition::STATUS_PENDING_APPROVAL, $requisition->fresh()->status);
    }

    public function test_requester_cannot_approve_own_requisition_and_must_be_assigned_to_active_department(): void
    {
        $selfManagedDepartment = Department::create([
            'company_id' => $this->company->id,
            'code' => 'SELF',
            'name' => 'Área propia',
            'manager_user_id' => $this->requester->id,
        ]);
        $this->requester->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());
        $this->requester->update(['department_id' => $selfManagedDepartment->id]);

        $this->actingAs($this->requester)->post(route('purchasing.requisitions.store'), $this->requisitionData());
        $requisition = Requisition::query()->firstOrFail();
        $this->post(route('purchasing.requisitions.submit', $requisition))
            ->assertSessionHasErrors('approval');
        $this->assertSame(Requisition::STATUS_DRAFT, $requisition->fresh()->status);

        $unassigned = User::factory()->create();
        $unassigned->roles()->attach(Role::query()->where('slug', 'solicitante')->firstOrFail());
        $this->actingAs($unassigned)
            ->post(route('purchasing.requisitions.store'), $this->requisitionData())
            ->assertSessionHasErrors('department');
    }

    public function test_requisition_data_is_validated_and_only_the_requester_can_edit_it(): void
    {
        $invalid = $this->requisitionData();
        $invalid['items'][0]['quantity'] = '0';
        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.store'), $invalid)
            ->assertSessionHasErrors('items.0.quantity');

        $this->post(route('purchasing.requisitions.store'), $this->requisitionData());
        $requisition = Requisition::query()->firstOrFail();
        $otherRequester = User::factory()->create();
        $otherRequester->roles()->attach(Role::query()->where('slug', 'solicitante')->firstOrFail());
        $otherRequester->update(['department_id' => $this->department->id]);

        $this->actingAs($otherRequester)
            ->put(route('purchasing.requisitions.update', $requisition), $this->requisitionData())
            ->assertForbidden();

        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.reject', $requisition), ['rejection_reason' => 'No'])
            ->assertForbidden();

        $this->post(route('purchasing.requisitions.submit', $requisition));
        $this->actingAs($this->manager)
            ->post(route('purchasing.requisitions.reject', $requisition), ['rejection_reason' => 'No'])
            ->assertSessionHasErrors('rejection_reason');
    }

    public function test_requisition_creation_requires_an_assigned_warehouse_and_records_its_scope(): void
    {
        $data = $this->requisitionData();
        unset($data['warehouse_id']);

        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.store'), $data)
            ->assertSessionHasErrors('warehouse_id');

        $otherBranch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'OTHER',
            'name' => 'Otra sede',
        ]);
        $otherWarehouse = Warehouse::create([
            'branch_id' => $otherBranch->id,
            'code' => 'OTHER',
            'name' => 'Bodega no asignada',
        ]);
        $data['warehouse_id'] = $otherWarehouse->id;

        $this->post(route('purchasing.requisitions.store'), $data)
            ->assertSessionHasErrors('warehouse_id');
        $this->assertSame(0, Requisition::query()->count());

        $data['warehouse_id'] = $this->warehouse->id;
        $this->post(route('purchasing.requisitions.store'), $data)->assertRedirect();
        $this->assertSame($this->warehouse->id, Requisition::query()->firstOrFail()->warehouse_id);
    }

    public function test_users_without_warehouse_access_cannot_view_or_decide_requisitions(): void
    {
        $this->actingAs($this->requester)->post(route('purchasing.requisitions.store'), $this->requisitionData());
        $requisition = Requisition::query()->firstOrFail();
        $managerWithoutWarehouse = User::factory()->create();
        $managerWithoutWarehouse->roles()->attach(Role::query()->where('slug', 'compras')->firstOrFail());

        $this->actingAs($managerWithoutWarehouse)
            ->get(route('purchasing.requisitions.index'))
            ->assertOk()
            ->assertDontSee($requisition->reference);

        $this->get(route('purchasing.requisitions.show', $requisition))
            ->assertForbidden();

        $requisition->update([
            'status' => Requisition::STATUS_PENDING_APPROVAL,
            'approver_user_id' => $managerWithoutWarehouse->id,
        ]);
        $this->actingAs($managerWithoutWarehouse)
            ->post(route('purchasing.requisitions.approve', $requisition))
            ->assertForbidden();

        $this->assertSame(Requisition::STATUS_PENDING_APPROVAL, $requisition->fresh()->status);
    }

    public function test_user_can_transfer_only_between_two_authorized_distinct_warehouses(): void
    {
        $otherWarehouse = Warehouse::create([
            'branch_id' => $this->warehouse->branch_id,
            'code' => 'SECOND',
            'name' => 'Bodega secundaria',
        ]);
        $this->requester->warehouses()->attach($otherWarehouse);

        $this->assertTrue($this->requester->canTransferBetweenWarehouses($this->warehouse->id, $otherWarehouse->id));
        $this->assertFalse($this->requester->canTransferBetweenWarehouses($this->warehouse->id, $this->warehouse->id));

        $unassignedWarehouse = Warehouse::create([
            'branch_id' => $this->warehouse->branch_id,
            'code' => 'THIRD',
            'name' => 'Bodega sin asignación',
        ]);
        $this->assertFalse($this->requester->canTransferBetweenWarehouses($this->warehouse->id, $unassignedWarehouse->id));
    }

    public function test_areas_can_only_assign_active_users_with_requisition_decision_permission(): void
    {
        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
        $this->actingAs($administrator)
            ->get(route('admin.organization.departments.index'))
            ->assertOk();
        $this->get(route('admin.organization.departments.create'))->assertOk();

        $unauthorizedManager = User::factory()->create();
        $this->post(route('admin.organization.departments.store'), [
            'code' => 'FIN',
            'name' => 'Finanzas',
            'manager_user_id' => $unauthorizedManager->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('departments', ['code' => 'FIN']);

        $this->post(route('admin.organization.departments.store'), [
            'code' => 'FIN',
            'name' => 'Finanzas',
            'manager_user_id' => $this->manager->id,
        ])->assertRedirect();
        $department = Department::query()->where('code', 'FIN')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->company->id,
            'action' => 'created',
            'entity_type' => 'department',
            'entity_id' => $department->id,
        ]);
        $this->get(route('admin.organization.departments.edit', $department))->assertOk();
    }

    private function requisitionData(): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'justification' => 'Materiales requeridos para el mantenimiento.',
            'needed_by' => now()->addDays(5)->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '2.500', 'notes' => 'Presentación estándar'],
            ],
        ];
    }
}
