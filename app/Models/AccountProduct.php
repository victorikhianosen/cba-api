<?php

declare(strict_types=1);

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
            'interest_rate'                     => 'decimal:2',
            'min_required_opening_balance'       => 'decimal:2',
            'min_required_balance'               => 'decimal:2',
            'overdraft_limit'                    => 'decimal:2',
            'overdraft_interest_rate'            => 'decimal:2',
            'enforce_min_required_balance'        => 'boolean',
            'allow_overdraft'                    => 'boolean',
            'withhold_tax'                       => 'boolean',
            'approved_at'                        => 'datetime',

            // Savings/current fee terms.
            'withdrawal_fee_amount'               => 'decimal:2',
            'annual_fee_amount'                   => 'decimal:2',
            'min_balance_for_interest_calculation' => 'decimal:2',
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
