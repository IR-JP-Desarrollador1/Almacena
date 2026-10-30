<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ISSUE = 'issue';

    protected $fillable = [
        'warehouse_lot_id',
        'warehouse_id',
        'product_id',
        'user_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity',
        'unit_cost_base',
        'base_currency_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(WarehouseLot::class, 'warehouse_lot_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'reference_id');
    }

    public function inventoryIssue(): BelongsTo
    {
        return $this->belongsTo(InventoryIssue::class, 'reference_id');
    }
}
