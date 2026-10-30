<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'tax_id',
        'legal_name',
        'address',
        'phone',
        'email',
        'legal_representative_name',
        'country',
        'administrative_division',
        'city',
        'base_currency_id',
        'regional_country_code',
        'language',
        'decimal_separator',
        'thousands_separator',
        'date_format',
        'time_format',
        'status',
    ];

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
