<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->foreignId('manager_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'manager_user_id']);
        });

        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->text('justification');
            $table->date('needed_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['approver_user_id', 'status']);
            $table->index(['requester_user_id', 'status']);
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('product_code', 50);
            $table->string('product_name');
            $table->string('unit_abbreviation', 20);
            $table->decimal('quantity', 15, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'requisition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
        Schema::dropIfExists('departments');
    }
};
