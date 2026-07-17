<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LoanProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'interest_rate'                        => 'decimal:6',
            'min_nominal_interest_rate_per_period'  => 'decimal:6',
            'max_nominal_interest_rate_per_period'  => 'decimal:6',
            'min_principal_amount'                  => 'decimal:6',
            'max_principal_amount'                  => 'decimal:6',
            'default_principal_amount'              => 'decimal:6',
            'arrears_tolerance_amount'               => 'decimal:6',
            'approved_at'                            => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function generalLedgerMappings(): MorphMany
    {
        return $this->morphMany(ProductToGlAccountMapping::class, 'product');
    }
}
