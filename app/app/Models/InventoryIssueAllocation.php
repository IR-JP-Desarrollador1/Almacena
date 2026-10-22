<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryIssueAllocation extends Model
{
    protected $fillable = [
        'inventory_issue_item_id',
        'warehouse_lot_id',
        'quantity',
        'unit_cost',
        'unit_cost_base',
    ];

    public function issueItem(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueItem::class, 'inventory_issue_item_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(WarehouseLot::class, 'warehouse_lot_id');
    }
}
