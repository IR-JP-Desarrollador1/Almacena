<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('currencies')->insert([
            ['code' => 'COP', 'name' => 'Peso colombiano', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'GBP', 'name' => 'Libra esterlina', 'symbol' => '£', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CAD', 'name' => 'Dólar canadiense', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MXN', 'name' => 'Peso mexicano', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'BRL', 'name' => 'Real brasileño', 'symbol' => 'R$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CLP', 'name' => 'Peso chileno', 'symbol' => '$', 'decimal_places' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ARS', 'name' => 'Peso argentino', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PEN', 'name' => 'Sol peruano', 'symbol' => 'S/', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CNY', 'name' => 'Yuan renminbi chino', 'symbol' => '¥', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'RUB', 'name' => 'Rublo ruso', 'symbol' => '₽', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('base_currency_id')->nullable()->after('city')->constrained('currencies')->restrictOnDelete();
        });

        $copId = DB::table('currencies')->where('code', 'COP')->value('id');
        DB::table('companies')->whereNull('base_currency_id')->update(['base_currency_id' => $copId]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('base_currency_id');
        });
        Schema::dropIfExists('currencies');
    }
};
