<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => 'audit.manage'],
            [
                'name' => 'Depurar registros de auditoría',
                'module' => 'seguridad',
                'description' => 'Eliminar registros de auditoría por rango de fechas o con más de 30 días de antigüedad.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $roleId = DB::table('roles')->where('slug', 'administracion')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'audit.manage')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'audit.manage')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
