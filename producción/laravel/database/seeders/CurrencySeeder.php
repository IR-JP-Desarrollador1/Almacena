<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'COP', 'name' => 'Peso colombiano', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'GBP', 'name' => 'Libra esterlina', 'symbol' => '£', 'decimal_places' => 2],
            ['code' => 'CAD', 'name' => 'Dólar canadiense', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'MXN', 'name' => 'Peso mexicano', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'BRL', 'name' => 'Real brasileño', 'symbol' => 'R$', 'decimal_places' => 2],
            ['code' => 'CLP', 'name' => 'Peso chileno', 'symbol' => '$', 'decimal_places' => 0],
            ['code' => 'ARS', 'name' => 'Peso argentino', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'PEN', 'name' => 'Sol peruano', 'symbol' => 'S/', 'decimal_places' => 2],
            ['code' => 'CNY', 'name' => 'Yuan renminbi chino', 'symbol' => '¥', 'decimal_places' => 2],
            ['code' => 'RUB', 'name' => 'Rublo ruso', 'symbol' => '₽', 'decimal_places' => 2],
        ] as $currency) {
            Currency::query()->firstOrCreate(
                ['code' => $currency['code']],
                [...$currency, 'is_active' => true],
            );
        }
    }
}
