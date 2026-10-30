<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->string('source_type', 20)->default('purchase_order');
            $table->string('direct_reason', 50)->nullable();
            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('purchase_orders')
                ->restrictOnDelete();
        });

        Schema::table('receipt_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);
            $table->foreignId('purchase_order_item_id')->nullable()->change();
            $table->foreign('purchase_order_item_id')
                ->references('id')
                ->on('purchase_order_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('receipts')->where('source_type', 'direct')->exists()) {
            throw new RuntimeException('Direct receipts exist and must be migrated before this migration can be rolled back.');
        }

        Schema::table('receipt_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);
            $table->foreignId('purchase_order_item_id')->nullable(false)->change();
            $table->foreign('purchase_order_item_id')
                ->references('id')
                ->on('purchase_order_items')
                ->restrictOnDelete();
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn(['source_type', 'direct_reason']);
            $table->foreignId('purchase_order_id')->nullable(false)->change();
            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('purchase_orders')
                ->restrictOnDelete();
        });
    }
};
