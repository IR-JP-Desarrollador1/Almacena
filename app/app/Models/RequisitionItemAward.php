<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItemAward extends Model
{
    protected $fillable = [
        'requisition_item_id',
        'supplier_quote_item_id',
        'selected_by_user_id',
        'justification',
        'selected_at',
    ];

    protected function casts(): array
    {
        return ['selected_at' => 'datetime'];
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteItem::class, 'supplier_quote_item_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }
}
