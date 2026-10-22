<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseLot extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'receipt_item_id',
        'lot_code',
        'quantity_received',
        'unit_cost',
        'unit_cost_base',
        'quote_currency_id',
        'base_currency_id',
        'exchange_rate',
        'expires_on',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_currency_id' => 'integer',
            'base_currency_id' => 'integer',
            'exchange_rate' => 'decimal:8',
            'expires_on' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ReceiptItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
