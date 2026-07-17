<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AccountProduct extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'interest_rate'                     => 'decimal:6',
            'min_required_opening_balance'       => 'decimal:6',
            'min_required_balance'               => 'decimal:6',
            'overdraft_limit'                    => 'decimal:6',
            'overdraft_interest_rate'            => 'decimal:6',
            'max_allowed_lien_limit'             => 'decimal:6',
            'enforce_min_required_balance'        => 'boolean',
            'allow_overdraft'                    => 'boolean',
            'withhold_tax'                       => 'boolean',
            'is_lien_allowed'                    => 'boolean',
            'approved_at'                        => 'datetime',
            'start_date'                          => 'date',
            'close_date'                          => 'date',

            // Savings/current fee terms.
            'withdrawal_fee_amount'               => 'decimal:6',
            'annual_fee_amount'                   => 'decimal:6',
            'min_balance_for_interest_calculation' => 'decimal:6',
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
