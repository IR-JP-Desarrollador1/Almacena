<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryIssueItem extends Model
{
    protected $fillable = [
        'inventory_issue_id',
        'requisition_item_id',
        'product_id',
        'product_code',
        'product_name',
        'unit_abbreviation',
        'quantity',
    ];

    public function inventoryIssue(): BelongsTo
    {
        return $this->belongsTo(InventoryIssue::class);
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryIssueAllocation::class);
    }
}
