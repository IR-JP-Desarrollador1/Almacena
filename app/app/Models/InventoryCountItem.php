<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    protected $fillable = [
        'inventory_count_id',
        'product_id',
        'product_code',
        'product_name',
        'unit_abbreviation',
        'system_quantity',
        'movement_id_snapshot',
        'counted_quantity',
        'difference_quantity',
        'surplus_unit_cost',
        'expires_on',
    ];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
