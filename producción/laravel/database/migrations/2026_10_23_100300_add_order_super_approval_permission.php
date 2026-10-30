<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => 'purchases.orders.approve_any'],
            [
                'name' => 'Aprobar cualquier orden de compra (Administración)',
                'module' => 'compras',
                'description' => 'Permite al perfil Administración aprobar órdenes pendientes incluso si fueron creadas por el mismo usuario.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $roleId = DB::table('roles')->where('slug', 'administracion')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'purchases.orders.approve_any')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'purchases.orders.approve_any')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
