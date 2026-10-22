<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReceiptItem extends Model
{
    protected $fillable = [
        'receipt_id',
        'purchase_order_item_id',
        'product_id',
        'quantity',
        'unit_cost',
        'unit_cost_base',
        'expires_on',
    ];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(WarehouseLot::class);
    }
}
