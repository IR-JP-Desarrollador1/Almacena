<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'user_id']);
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropIndex(['warehouse_id', 'status']);
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::dropIfExists('user_warehouse');
    }
};
