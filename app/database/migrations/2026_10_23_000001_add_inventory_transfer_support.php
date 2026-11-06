<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->foreignId('destination_warehouse_id')->nullable()->after('warehouse_id')->constrained('warehouses')->restrictOnDelete();
        });

        Schema::table('warehouse_lots', function (Blueprint $table) {
            $table->unsignedBigInteger('receipt_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->dropForeign(['destination_warehouse_id']);
            $table->dropColumn('destination_warehouse_id');
        });

        Schema::table('warehouse_lots', function (Blueprint $table) {
            $table->dropForeign(['receipt_item_id']);
            $table->unsignedBigInteger('receipt_item_id')->nullable(false)->change();
            $table->foreign('receipt_item_id')->references('id')->on('receipt_items')->restrictOnDelete();
        });
    }
};
