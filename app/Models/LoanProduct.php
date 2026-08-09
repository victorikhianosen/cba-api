<?php

declare(strict_types=1);

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
            'interest_rate'                        => 'decimal:2',
            'min_nominal_interest_rate_per_period'  => 'decimal:2',
            'max_nominal_interest_rate_per_period'  => 'decimal:2',
            'min_principal_amount'                  => 'integer',
            'max_principal_amount'                  => 'integer',
            'default_principal_amount'              => 'integer',
            'arrears_tolerance_amount'               => 'integer',
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
        return $this->morphMany(ProductGlMapping::class, 'product');
    }
}
