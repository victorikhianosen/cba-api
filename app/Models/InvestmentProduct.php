<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvestmentProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'interest_rate'                => 'decimal:6',
            'min_required_opening_balance'  => 'decimal:6',
            'min_required_balance'          => 'decimal:6',
            'min_deposit_amount'            => 'decimal:6',
            'max_deposit_amount'            => 'decimal:6',
            'pre_closure_penal_applicable'  => 'boolean',
            'pre_closure_penal_interest'    => 'decimal:6',
            'approved_at'                  => 'datetime',
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
