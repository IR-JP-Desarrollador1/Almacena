<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function companiesUsingAsBase(): HasMany
    {
        return $this->hasMany(Company::class, 'base_currency_id');
    }

    public function purchaseOrdersUsingAsQuote(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'quote_currency_id');
    }

    public function purchaseOrdersUsingAsBase(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'base_currency_id');
    }
}
