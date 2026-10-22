<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuote extends Model
{
    protected $fillable = [
        'requisition_id',
        'supplier_id',
        'quote_reference',
        'quote_date',
        'quote_currency_id',
        'base_currency_id',
        'exchange_rate',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'exchange_rate' => 'decimal:8',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteItem::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
