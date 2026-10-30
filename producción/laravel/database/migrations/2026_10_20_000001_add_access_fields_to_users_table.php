<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('last_name')->nullable()->after('name');
            $table->string('status', 20)->default('pending')->index()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->boolean('must_change_password')->default(false)->after('last_login_at');
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attempted_email')->nullable();
            $table->string('result', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['attempted_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['last_name', 'phone', 'status', 'last_login_at', 'must_change_password']);
        });
    }
};
