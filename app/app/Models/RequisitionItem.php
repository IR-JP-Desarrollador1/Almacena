<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id',
        'product_id',
        'unit_id',
        'product_code',
        'product_name',
        'unit_abbreviation',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventoryIssueItems(): HasMany
    {
        return $this->hasMany(InventoryIssueItem::class);
    }
}
