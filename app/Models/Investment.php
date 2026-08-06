<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'principal_amount'                => 'decimal:2',
            'interest_rate'                   => 'decimal:2',
            'tenure'                           => 'integer',
            'early_liquidation_lock_days'      => 'integer',
            'early_liquidation_penalty_rate'   => 'decimal:2',
            'withholding_tax_rate'             => 'decimal:2',
            'start_date'                       => 'date',
            'maturity_date'                    => 'date',
            'auto_renew'                       => 'boolean',
            'current_principal'                => 'decimal:2',
            'minimum_retention_percentage'     => 'decimal:2',
            'total_top_up_amount'              => 'decimal:2',
            'pending_topup_amount'             => 'decimal:2',
            'pending_topup_requested_at'       => 'datetime',
            'metadata'                         => 'array',
            'total_withdrawal_amount'          => 'decimal:2',
            'total_interest_accrued'           => 'decimal:2',
            'total_interest_paid'              => 'decimal:2',
            'outstanding_interest'             => 'decimal:2',
            'total_fees_charged'               => 'decimal:2',
            'total_penalties_charged'          => 'decimal:2',
            'total_withholding_tax_deducted'   => 'decimal:2',
            'net_interest_paid'                => 'decimal:2',
            'maturity_value'                   => 'decimal:2',
            'last_interest_payment_date'       => 'date',
            'next_interest_payment_date'       => 'date',
            'approved_at'                      => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function investmentProduct(): BelongsTo
    {
        return $this->belongsTo(InvestmentProduct::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
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

    public function pendingTopupRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pending_topup_requested_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InvestmentSchedule::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }
}
