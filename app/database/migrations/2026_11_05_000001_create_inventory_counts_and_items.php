<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('return_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_code', 50);
            $table->string('product_name');
            $table->string('unit_abbreviation', 20);
            $table->decimal('system_quantity', 15, 3)->nullable();
            $table->unsignedBigInteger('movement_id_snapshot')->nullable();
            $table->decimal('counted_quantity', 15, 3);
            $table->decimal('difference_quantity', 15, 3)->nullable();
            $table->decimal('surplus_unit_cost', 24, 4)->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();
            $table->unique(['inventory_count_id', 'product_id'], 'inventory_count_item_product_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('inventory_counts')->exists()) {
            throw new RuntimeException('Inventory count records exist; refusing to remove their schema.');
        }

        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
    }
};
