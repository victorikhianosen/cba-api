<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRefinance extends Model
{
    protected $fillable = [
        'old_loan_id', 'new_loan_id', 'reference', 'loan_product_id', 'old_outstanding_amount',
        'additional_cash_out', 'new_principal_amount', 'new_duration',
        'new_interest_rate', 'reason', 'status', 'requested_by', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason', 'disbursement_account_id',
    ];

    protected $casts = [
        'old_outstanding_amount' => 'decimal:2',
        'additional_cash_out'    => 'decimal:2',
        'new_principal_amount'   => 'decimal:2',
        'new_interest_rate'      => 'decimal:2',
        'approved_at'            => 'datetime',
        'rejected_at'            => 'datetime',
    ];

    public function oldLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'old_loan_id');
    }

    public function newLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'new_loan_id');
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function disbursementAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'disbursement_account_id');
    }
}
