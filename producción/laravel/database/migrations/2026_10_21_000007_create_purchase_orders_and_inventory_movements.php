<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 30)->unique();
                $table->foreignId('requisition_id')->unique()->constrained()->restrictOnDelete();
                $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignId('quote_currency_id')->constrained('currencies')->restrictOnDelete();
                $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 20, 8);
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->string('quote_reference', 100);
                $table->date('quote_date');
                $table->string('status', 30)->default('draft')->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->index(['supplier_id', 'status']);
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requisition_item_id')->unique()->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->string('product_code', 50);
                $table->string('product_name');
                $table->string('unit_abbreviation', 20);
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_cost', 15, 4);
                $table->decimal('unit_cost_base', 24, 4);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('receipts')) {
            Schema::create('receipts', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 30)->unique();
                $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
                $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
                $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
                $table->string('document_reference', 100)->nullable();
                $table->timestamp('received_at');
                $table->timestamps();
                $table->index(['warehouse_id', 'received_at']);
            });
        }

        if (! Schema::hasTable('receipt_items')) {
            Schema::create('receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_cost', 15, 4);
                $table->decimal('unit_cost_base', 24, 4);
                $table->date('expires_on')->nullable();
                $table->timestamps();
                $table->index(['purchase_order_item_id', 'receipt_id']);
            });
        }

        if (! Schema::hasTable('warehouse_lots')) {
            Schema::create('warehouse_lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('receipt_item_id')->unique()->constrained()->restrictOnDelete();
                $table->string('lot_code', 40)->unique();
                $table->decimal('quantity_received', 15, 3);
                $table->decimal('unit_cost', 15, 4);
                $table->decimal('unit_cost_base', 24, 4);
                $table->foreignId('quote_currency_id')->constrained('currencies')->restrictOnDelete();
                $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
                $table->decimal('exchange_rate', 20, 8);
                $table->date('expires_on')->nullable();
                $table->timestamp('received_at');
                $table->timestamps();
                $table->index(['warehouse_id', 'product_id', 'expires_on']);
            });
        }

        if (! Schema::hasTable('inventory_issues')) {
            Schema::create('inventory_issues', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 30)->unique();
                $table->foreignId('requisition_id')->constrained()->restrictOnDelete();
                $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
                $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
                $table->string('document_reference', 100)->nullable();
                $table->timestamp('issued_at');
                $table->timestamps();
                $table->index(['warehouse_id', 'issued_at']);
            });
        }

        if (! Schema::hasTable('inventory_issue_items')) {
            Schema::create('inventory_issue_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_issue_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requisition_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->string('product_code', 50);
                $table->string('product_name');
                $table->string('unit_abbreviation', 20);
                $table->decimal('quantity', 15, 3);
                $table->timestamps();
                $table->index(['requisition_item_id', 'inventory_issue_id'], 'issue_items_requisition_issue_idx');
            });
        }
        if (! Schema::hasIndex('inventory_issue_items', 'issue_items_requisition_issue_idx')) {
            Schema::table('inventory_issue_items', function (Blueprint $table) {
                $table->index(['requisition_item_id', 'inventory_issue_id'], 'issue_items_requisition_issue_idx');
            });
        }

        if (! Schema::hasTable('inventory_issue_allocations')) {
            Schema::create('inventory_issue_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_issue_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_lot_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_cost', 15, 4);
                $table->decimal('unit_cost_base', 24, 4);
                $table->timestamps();
                $table->index(['warehouse_lot_id', 'inventory_issue_item_id'], 'issue_alloc_lot_item_idx');
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_lot_id')->constrained()->restrictOnDelete();
                $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('movement_type', 20);
                $table->string('reference_type', 30);
                $table->unsignedBigInteger('reference_id');
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_cost_base', 24, 4);
                $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index(['warehouse_id', 'product_id', 'occurred_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_issue_allocations');
        Schema::dropIfExists('inventory_issue_items');
        Schema::dropIfExists('inventory_issues');
        Schema::dropIfExists('warehouse_lots');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
