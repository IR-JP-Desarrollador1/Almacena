<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluation extends Model
{
    protected $fillable = [
        'supplier_id',
        'evaluated_by_user_id',
        'distance_score',
        'delivery_time_score',
        'fulfillment_score',
        'average_score',
    ];

    protected function casts(): array
    {
        return [
            'distance_score' => 'integer',
            'delivery_time_score' => 'integer',
            'fulfillment_score' => 'integer',
            'average_score' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }
}
