<?php

declare(strict_types=1);

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
            'interest_rate'                    => 'decimal:6',
            'tenure'                           => 'integer',
            'min_deposit_amount'               => 'decimal:6',
            'max_deposit_amount'               => 'decimal:6',
            'auto_renew'                       => 'boolean',
            'allow_top_up'                     => 'boolean',
            'allow_partial_withdrawal'         => 'boolean',
            'allow_early_liquidation'          => 'boolean',
            'early_liquidation_lock_days'      => 'integer',
            'early_liquidation_penalty_rate'   => 'decimal:6',
            'is_default'                       => 'boolean',
            'approved_at'                      => 'datetime',
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
