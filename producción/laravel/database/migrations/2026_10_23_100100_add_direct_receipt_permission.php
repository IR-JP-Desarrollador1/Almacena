<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => 'inventory.receipts.direct.create'],
            [
                'name' => 'Registrar entradas directas de inventario',
                'module' => 'inventario',
                'description' => 'Registrar entradas autorizadas sin orden de compra, con motivo y costo unitario en moneda base.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = DB::table('permissions')
            ->where('code', 'inventory.receipts.direct.create')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['administracion', 'almacen'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }

    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('code', 'inventory.receipts.direct.create')
            ->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
