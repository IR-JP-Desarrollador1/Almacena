<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NEW_PERMISSIONS = [
        ['name' => 'Aprobar requisiciones asignadas', 'code' => 'purchases.requisitions.approve', 'module' => 'compras', 'description' => 'Aprobar requisiciones pendientes asignadas al jefe del área.'],
        ['name' => 'Rechazar requisiciones asignadas', 'code' => 'purchases.requisitions.reject', 'module' => 'compras', 'description' => 'Rechazar requisiciones pendientes asignadas al jefe del área.'],
        ['name' => 'Enviar requisiciones a aprobación', 'code' => 'inventory.requisitions.submit', 'module' => 'inventario', 'description' => 'Enviar el saldo pendiente de una requisición activa al jefe del área.'],
        ['name' => 'Registrar salidas parciales de requisiciones', 'code' => 'inventory.requisitions.dispatch', 'module' => 'inventario', 'description' => 'Registrar entregas parciales de productos para requisiciones activas o aprobadas.'],
        ['name' => 'Consultar comparación de cotizaciones', 'code' => 'purchases.quotes.view', 'module' => 'compras', 'description' => 'Consultar ofertas y comparaciones de las requisiciones aprobadas.'],
        ['name' => 'Registrar cotizaciones de proveedores', 'code' => 'purchases.quotes.manage', 'module' => 'compras', 'description' => 'Crear, completar o actualizar las ofertas recibidas para una requisición.'],
        ['name' => 'Adjudicar ofertas de cotización', 'code' => 'purchases.quotes.award', 'module' => 'compras', 'description' => 'Seleccionar la oferta ganadora por producto y guardar la comparación.'],
        ['name' => 'Generar órdenes desde cotizaciones', 'code' => 'purchases.quotes.generate_orders', 'module' => 'compras', 'description' => 'Generar órdenes de compra borrador a partir de ofertas adjudicadas.'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::NEW_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [...$permission, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $this->copyRoleAssignments('purchases.requisitions.decide', [
            'purchases.requisitions.approve',
            'purchases.requisitions.reject',
        ]);
        DB::table('permissions')->where('code', 'purchases.requisitions.decide')->delete();
        $this->copyRoleAssignments('inventory.requisitions.manage', ['inventory.requisitions.submit']);
        $this->copyRoleAssignments('inventory.issues.create', ['inventory.requisitions.dispatch']);
        $this->copyRoleAssignments('purchases.orders.create', [
            'purchases.quotes.view',
            'purchases.quotes.manage',
            'purchases.quotes.award',
            'purchases.quotes.generate_orders',
        ]);
    }

    public function down(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Aprobar o rechazar requisiciones asignadas',
            'code' => 'purchases.requisitions.decide',
            'module' => 'compras',
            'description' => 'Resolver requisiciones pendientes asignadas al jefe del área.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Crear órdenes de compra',
            'code' => 'purchases.orders.create',
            'module' => 'compras',
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->copyRoleAssignments('inventory.requisitions.dispatch', ['inventory.issues.create']);
        $this->copyRoleAssignments('purchases.quotes.view', ['purchases.orders.create']);
        $this->copyRoleAssignments('purchases.quotes.manage', ['purchases.orders.create']);
        $this->copyRoleAssignments('purchases.quotes.award', ['purchases.orders.create']);
        $this->copyRoleAssignments('purchases.quotes.generate_orders', ['purchases.orders.create']);
        $this->copyRoleAssignments('inventory.requisitions.submit', ['inventory.requisitions.manage']);
        $this->copyRoleAssignments('purchases.requisitions.approve', ['purchases.requisitions.decide']);
        $this->copyRoleAssignments('purchases.requisitions.reject', ['purchases.requisitions.decide']);

        DB::table('permissions')->whereIn('code', array_column(self::NEW_PERMISSIONS, 'code'))->delete();
    }

    private function copyRoleAssignments(string $sourceCode, array $targetCodes): void
    {
        $sourceId = DB::table('permissions')->where('code', $sourceCode)->value('id');
        if (! $sourceId) {
            return;
        }

        $targetIds = DB::table('permissions')->whereIn('code', $targetCodes)->pluck('id');
        $roleIds = DB::table('permission_role')->where('permission_id', $sourceId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            foreach ($targetIds as $targetId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }
};
