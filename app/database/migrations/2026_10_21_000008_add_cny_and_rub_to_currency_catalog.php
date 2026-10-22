<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('currencies')->insertOrIgnore([
            ['code' => 'CNY', 'name' => 'Yuan renminbi chino', 'symbol' => '¥', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'RUB', 'name' => 'Rublo ruso', 'symbol' => '₽', 'decimal_places' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('currencies')
            ->whereIn('code', ['CNY', 'RUB'])
            ->whereNotIn('id', function ($query) {
                $query->select('base_currency_id')->from('companies')->whereNotNull('base_currency_id');
            })
            ->whereNotIn('id', function ($query) {
                $query->select('quote_currency_id')->from('purchase_orders');
            })
            ->whereNotIn('id', function ($query) {
                $query->select('base_currency_id')->from('purchase_orders');
            })
            ->delete();
    }
};
