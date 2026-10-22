<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyId = DB::table('permissions')->where('code', 'purchases.orders.create')->value('id');
        if (! $legacyId) {
            return;
        }

        $quotePermissionIds = DB::table('permissions')
            ->whereIn('code', [
                'purchases.quotes.view',
                'purchases.quotes.manage',
                'purchases.quotes.award',
                'purchases.quotes.generate_orders',
            ])
            ->pluck('id');
        $roleIds = DB::table('permission_role')->where('permission_id', $legacyId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            foreach ($quotePermissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        DB::table('permissions')->where('id', $legacyId)->delete();
    }

    public function down(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Crear órdenes de compra',
            'code' => 'purchases.orders.create',
            'module' => 'compras',
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $legacyId = DB::table('permissions')->where('code', 'purchases.orders.create')->value('id');
        $quotePermissionIds = DB::table('permissions')
            ->whereIn('code', [
                'purchases.quotes.view',
                'purchases.quotes.manage',
                'purchases.quotes.award',
                'purchases.quotes.generate_orders',
            ])
            ->pluck('id');
        $roleIds = DB::table('permission_role')->whereIn('permission_id', $quotePermissionIds)->pluck('role_id')->unique();
        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $legacyId,
                'role_id' => $roleId,
            ]);
        }
    }
};
