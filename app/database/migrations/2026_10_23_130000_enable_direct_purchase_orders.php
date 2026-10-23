<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('purchase_orders')
            ->join('requisitions', 'requisitions.id', '=', 'purchase_orders.requisition_id')
            ->whereNull('requisitions.warehouse_id')
            ->exists()) {
            throw new RuntimeException('Every existing purchase order must have a requisition warehouse before direct orders are enabled.');
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['requisition_id']);
            $table->unsignedBigInteger('requisition_id')->nullable()->change();
            $table->foreign('requisition_id')->references('id')->on('requisitions')->restrictOnDelete();
            $table->string('quote_reference', 100)->nullable()->change();
            $table->date('quote_date')->nullable()->change();
            $table->foreignId('warehouse_id')->nullable()->after('requisition_id')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('purchase_orders')->get(['id', 'requisition_id']) as $order) {
            $warehouseId = DB::table('requisitions')->where('id', $order->requisition_id)->value('warehouse_id');
            DB::table('purchase_orders')->where('id', $order->id)->update(['warehouse_id' => $warehouseId]);
        }

        if (DB::table('purchase_orders')->whereNull('warehouse_id')->exists()) {
            throw new RuntimeException('Every existing purchase order must have a warehouse before direct orders are enabled.');
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['requisition_item_id']);
            $table->unsignedBigInteger('requisition_item_id')->nullable()->change();
            $table->foreign('requisition_item_id')->references('id')->on('requisition_items')->restrictOnDelete();
        });

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            [
                'name' => 'Crear órdenes de compra directas',
                'code' => 'purchases.orders.create_direct',
                'module' => 'compras',
                'description' => 'Crear órdenes sin cotización desde requisiciones aprobadas o sin requisición.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $directPermissionId = DB::table('permissions')->where('code', 'purchases.orders.create_direct')->value('id');
        $creatorPermissionIds = DB::table('permissions')
            ->whereIn('code', ['purchases.quotes.generate_orders', 'purchases.orders.submit'])
            ->pluck('id');
        $roleIds = DB::table('permission_role')->whereIn('permission_id', $creatorPermissionIds)->distinct()->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $directPermissionId,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('purchase_orders')->whereNull('requisition_id')
            ->orWhereNull('supplier_quote_id')
            ->orWhereNull('quote_reference')
            ->orWhereNull('quote_date')
            ->exists()
            || DB::table('purchase_order_items')->whereNull('requisition_item_id')->exists()) {
            throw new RuntimeException('Direct purchase orders exist; refusing to remove their schema.');
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['requisition_item_id']);
            $table->unsignedBigInteger('requisition_item_id')->nullable(false)->change();
            $table->foreign('requisition_item_id')->references('id')->on('requisition_items')->restrictOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropForeign(['requisition_id']);
            $table->unsignedBigInteger('requisition_id')->nullable(false)->change();
            $table->foreign('requisition_id')->references('id')->on('requisitions')->restrictOnDelete();
            $table->string('quote_reference', 100)->nullable(false)->change();
            $table->date('quote_date')->nullable(false)->change();
        });

        $permissionId = DB::table('permissions')->where('code', 'purchases.orders.create_direct')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
