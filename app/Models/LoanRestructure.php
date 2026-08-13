<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRestructure extends Model
{
    protected $fillable = [
        'loan_id', 'reference', 'reason', 'new_duration',
        'new_interest_rate', 'capitalized_interest', 'capitalized_fees', 'waived_amount', 'restructure_fee',
        'status', 'requested_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at',
        'rejection_reason', 'old_schedule_version', 'new_schedule_version',
    ];

    protected $casts = [
        'new_interest_rate'     => 'decimal:2',
        'capitalized_interest'  => 'decimal:2',
        'capitalized_fees'      => 'decimal:2',
        'waived_amount'         => 'decimal:2',
        'restructure_fee'       => 'decimal:2',
        'approved_at'           => 'datetime',
        'rejected_at'           => 'datetime',
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
}
