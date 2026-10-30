<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('regional_country_code', 2)->default('CO');
            $table->string('language', 10)->default('es');
            $table->string('decimal_separator', 1)->default(',');
            $table->string('thousands_separator', 1)->default('.');
            $table->string('date_format', 10)->default('d/m/Y');
            $table->string('time_format', 10)->default('h:i A');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'regional_country_code',
                'language',
                'decimal_separator',
                'thousands_separator',
                'date_format',
                'time_format',
            ]);
        });
    }
};
