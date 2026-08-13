<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRecovery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'              => 'decimal:2',
            'principal_portion'   => 'decimal:2',
            'interest_portion'    => 'decimal:2',
            'penalty_portion'     => 'decimal:2',
            'fee_portion'         => 'decimal:2',
            'recovered_at'        => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recoveryOfficer(): BelongsTo
    {
        return $this->belongsTo(AccountOfficer::class, 'recovery_officer_id');
    }

    public function fundingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'funding_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
