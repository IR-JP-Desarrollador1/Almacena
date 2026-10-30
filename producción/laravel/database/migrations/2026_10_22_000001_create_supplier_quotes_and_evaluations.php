<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('quote_reference', 100);
            $table->date('quote_date');
            $table->foreignId('quote_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 20, 8);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['requisition_id', 'supplier_id'], 'uq_sq_requisition_supplier');
            $table->index(['supplier_id', 'quote_date']);
        });

        Schema::create('supplier_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_item_id')->constrained()->restrictOnDelete();
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('tax_rate', 7, 4);
            $table->string('brand', 100);
            $table->unsignedSmallInteger('delivery_days');
            $table->timestamps();
            $table->unique(['supplier_quote_id', 'requisition_item_id'], 'uq_sqi_quote_requisition_item');
            $table->index('requisition_item_id');
        });

        Schema::create('requisition_item_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_quote_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('selected_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('justification')->nullable();
            $table->timestamp('selected_at');
            $table->timestamps();
        });

        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('distance_score');
            $table->unsignedTinyInteger('delivery_time_score');
            $table->unsignedTinyInteger('fulfillment_score');
            $table->decimal('average_score', 3, 2);
            $table->timestamps();
            $table->index(['supplier_id', 'created_at']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['requisition_id']);
            $table->dropUnique(['requisition_id']);
            $table->index('requisition_id', 'purchase_orders_requisition_id_index');
            $table->foreign('requisition_id')->references('id')->on('requisitions')->restrictOnDelete();
            $table->foreignId('supplier_quote_id')->nullable()->after('requisition_id')->constrained('supplier_quotes')->restrictOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('supplier_quote_item_id')->nullable()->after('requisition_item_id')->constrained('supplier_quote_items')->restrictOnDelete();
            $table->decimal('tax_rate', 7, 4)->default(0)->after('unit_cost_base');
            $table->string('brand', 100)->nullable()->after('tax_rate');
            $table->unsignedSmallInteger('delivery_days')->nullable()->after('brand');
            $table->unique('supplier_quote_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropUnique(['supplier_quote_item_id']);
            $table->dropConstrainedForeignId('supplier_quote_item_id');
            $table->dropColumn(['tax_rate', 'brand', 'delivery_days']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_quote_id');
            $table->dropForeign(['requisition_id']);
            $table->dropIndex('purchase_orders_requisition_id_index');
            $table->unique('requisition_id');
            $table->foreign('requisition_id')->references('id')->on('requisitions')->restrictOnDelete();
        });

        Schema::dropIfExists('supplier_evaluations');
        Schema::dropIfExists('requisition_item_awards');
        Schema::dropIfExists('supplier_quote_items');
        Schema::dropIfExists('supplier_quotes');
    }
};
