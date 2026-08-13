<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $guarded = [];

    // Fallback when neither the loan nor its product has an explicit
    // payment_allocation_order set — the banking-standard order documented in
    // LoanRepaymentService.
    public const DEFAULT_PAYMENT_ALLOCATION_ORDER = ['penalty', 'fee', 'interest', 'principal'];

    protected function casts(): array
    {
        return [
            'principal_amount'            => 'decimal:2',
            'interest_rate'                => 'decimal:2',
            'repayment_every'              => 'integer',
            'duration'         => 'integer',
            'arrears_tolerance_amount'     => 'decimal:2',
            'penalty_interest_rate'        => 'decimal:2',
            'payment_allocation_order'     => 'array',
            'interest_suspended'           => 'boolean',
            'interest_suspended_at'        => 'datetime',
            'suspended_interest_balance'   => 'decimal:2',
            'start_date'                   => 'date',
            'maturity_date'                => 'date',
            'outstanding_principal'        => 'decimal:2',
            'outstanding_interest'         => 'decimal:2',
            'total_principal_disbursed'    => 'decimal:2',
            'total_principal_paid'         => 'decimal:2',
            'total_interest_paid'          => 'decimal:2',
            'total_fees_charged'           => 'decimal:2',
            'total_fees_paid'              => 'decimal:2',
            'total_penalties_charged'      => 'decimal:2',
            'total_penalties_paid'         => 'decimal:2',
            'overpayment_balance'          => 'decimal:2',
            'total_principal_waived'       => 'decimal:2',
            'total_interest_waived'        => 'decimal:2',
            'total_fees_waived'            => 'decimal:2',
            'total_penalties_waived'       => 'decimal:2',
            'written_off_principal'        => 'decimal:2',
            'written_off_interest'         => 'decimal:2',
            'total_principal_recovered'    => 'decimal:2',
            'total_interest_recovered'     => 'decimal:2',
            'total_penalty_recovered'      => 'decimal:2',
            'total_fee_recovered'          => 'decimal:2',
            'last_repayment_date'          => 'date',
            'next_due_date'                => 'date',
            'days_in_arrears'              => 'integer',
            'npl_classified_at'            => 'datetime',
            'written_off_at'               => 'datetime',
            'reviewed_at'                  => 'datetime',
            'approved_at'                  => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function disbursementAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'disbursement_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function loanOfficer(): BelongsTo
    {
        return $this->belongsTo(AccountOfficer::class, 'loan_officer_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class);
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(LoanCollateral::class);
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function writeOffs(): HasMany
    {
        return $this->hasMany(LoanWriteOff::class);
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(LoanRecovery::class);
    }

    public function collectionActivities(): HasMany
    {
        return $this->hasMany(LoanCollection::class);
    }

    public function restructures(): HasMany
    {
        return $this->hasMany(LoanRestructure::class);
    }

    public function topups(): HasMany
    {
        return $this->hasMany(LoanTopup::class);
    }

    public function refinances(): HasMany
    {
        return $this->hasMany(LoanRefinance::class, 'old_loan_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }
}
