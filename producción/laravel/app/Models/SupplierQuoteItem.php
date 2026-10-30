<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierQuoteItem extends Model
{
    protected $fillable = [
        'supplier_quote_id',
        'requisition_item_id',
        'unit_cost',
        'tax_rate',
        'brand',
        'delivery_days',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'delivery_days' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class, 'supplier_quote_id');
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(RequisitionItemAward::class);
    }

    public function purchaseOrderItem(): HasOne
    {
        return $this->hasOne(PurchaseOrderItem::class);
    }
}
