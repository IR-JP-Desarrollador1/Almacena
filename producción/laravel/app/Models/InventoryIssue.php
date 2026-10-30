<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryIssue extends Model
{
    public const SOURCE_REQUISITION = 'requisition';

    public const SOURCE_DIRECT = 'direct';

    protected $fillable = [
        'reference',
        'source_type',
        'direct_reason',
        'requisition_id',
        'warehouse_id',
        'issued_by_user_id',
        'document_reference',
        'issued_at',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryIssueItem::class);
    }
}
