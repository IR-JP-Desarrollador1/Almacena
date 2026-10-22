<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'reference',
        'requisition_id',
        'supplier_quote_id',
        'supplier_id',
        'quote_currency_id',
        'base_currency_id',
        'exchange_rate',
        'created_by_user_id',
        'approved_by_user_id',
        'quote_reference',
        'quote_date',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_currency_id' => 'integer',
            'base_currency_id' => 'integer',
            'exchange_rate' => 'decimal:8',
            'quote_date' => 'date',
            'approved_at' => 'datetime',
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

    public function supplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class);
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
