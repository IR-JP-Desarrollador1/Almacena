<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('rejected_by_user_id')->nullable()->after('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('rejection_reason', 2000)->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            [
                'name' => 'Enviar órdenes de compra a aprobación',
                'code' => 'purchases.orders.submit',
                'module' => 'compras',
                'description' => 'Enviar órdenes propias para revisión independiente.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Rechazar órdenes de compra',
                'code' => 'purchases.orders.reject',
                'module' => 'compras',
                'description' => 'Rechazar órdenes pendientes con un motivo y devolverlas a su creador para corrección.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $submitPermissionId = DB::table('permissions')->where('code', 'purchases.orders.submit')->value('id');
        $rejectPermissionId = DB::table('permissions')->where('code', 'purchases.orders.reject')->value('id');
        $generatePermissionId = DB::table('permissions')->where('code', 'purchases.quotes.generate_orders')->value('id');
        $approvePermissionId = DB::table('permissions')->where('code', 'purchases.orders.approve')->value('id');

        if ($generatePermissionId) {
            $roleIds = DB::table('permission_role')->where('permission_id', $generatePermissionId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $submitPermissionId,
                ]);
            }
        }

        if ($approvePermissionId) {
            $roleIds = DB::table('permission_role')->where('permission_id', $approvePermissionId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $rejectPermissionId,
                ]);
            }
        }

        $administrationId = DB::table('roles')->where('slug', 'administracion')->value('id');
        if ($administrationId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $administrationId,
                'permission_id' => $submitPermissionId,
            ]);
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $administrationId,
                'permission_id' => $rejectPermissionId,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('purchase_orders')
            ->whereIn('status', ['pending_approval', 'rejected'])
            ->orWhereNotNull('rejected_by_user_id')
            ->exists()) {
            throw new RuntimeException('Orders in the approval workflow must be resolved before this migration can be rolled back.');
        }

        foreach (['purchases.orders.submit', 'purchases.orders.reject'] as $code) {
            $permissionId = DB::table('permissions')->where('code', $code)->value('id');
            if ($permissionId) {
                DB::table('permission_role')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejection_reason', 'rejected_at']);
        });
    }
};
