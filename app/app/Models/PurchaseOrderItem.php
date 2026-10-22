<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'requisition_item_id',
        'supplier_quote_item_id',
        'product_id',
        'product_code',
        'product_name',
        'unit_abbreviation',
        'quantity',
        'unit_cost',
        'unit_cost_base',
        'tax_rate',
        'brand',
        'delivery_days',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function supplierQuoteItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }
}
