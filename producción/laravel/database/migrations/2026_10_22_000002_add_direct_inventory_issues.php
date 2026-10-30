<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->dropForeign(['requisition_id']);
        });

        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->unsignedBigInteger('requisition_id')->nullable()->change();
            $table->string('source_type', 20)->default('requisition');
            $table->string('direct_reason', 40)->nullable();
            $table->foreign('requisition_id')->references('id')->on('requisitions')->restrictOnDelete();
        });

        Schema::table('inventory_issue_items', function (Blueprint $table) {
            $table->dropForeign(['requisition_item_id']);
        });

        Schema::table('inventory_issue_items', function (Blueprint $table) {
            $table->unsignedBigInteger('requisition_item_id')->nullable()->change();
            $table->foreign('requisition_item_id')->references('id')->on('requisition_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('inventory_issues')->where('source_type', 'direct')->exists()) {
            throw new RuntimeException('Direct inventory issues exist; refusing to remove their schema.');
        }

        Schema::table('inventory_issue_items', function (Blueprint $table) {
            $table->dropForeign(['requisition_item_id']);
        });

        Schema::table('inventory_issue_items', function (Blueprint $table) {
            $table->unsignedBigInteger('requisition_item_id')->nullable(false)->change();
            $table->foreign('requisition_item_id')->references('id')->on('requisition_items')->restrictOnDelete();
        });

        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->dropForeign(['requisition_id']);
        });

        Schema::table('inventory_issues', function (Blueprint $table) {
            $table->unsignedBigInteger('requisition_id')->nullable(false)->change();
            $table->foreign('requisition_id')->references('id')->on('requisitions')->restrictOnDelete();
            $table->dropColumn(['source_type', 'direct_reason']);
        });
    }
};
