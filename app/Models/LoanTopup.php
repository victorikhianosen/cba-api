<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanTopup extends Model
{
    protected $fillable = [
        'loan_id', 'reference', 'topup_amount', 'principal_before', 'principal_after',
        'new_duration', 'new_interest_rate', 'reason',
        'status', 'requested_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at',
        'rejection_reason', 'disbursement_account_id', 'loan_transaction_id',
        'old_schedule_version', 'new_schedule_version',
    ];

    protected $casts = [
        'topup_amount'      => 'decimal:2',
        'principal_before'  => 'decimal:2',
        'principal_after'   => 'decimal:2',
        'new_interest_rate' => 'decimal:2',
        'approved_at'       => 'datetime',
        'rejected_at'       => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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
