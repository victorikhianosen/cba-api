<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date'                    => 'date',
            'opening_principal_balance'   => 'decimal:2',
            'principal_due'                => 'decimal:2',
            'interest_due'                 => 'decimal:2',
            'closing_principal_balance'   => 'decimal:2',
            'principal_paid'                => 'decimal:2',
            'interest_paid'                 => 'decimal:2',
            'penalty_due'                   => 'decimal:2',
            'penalty_paid'                  => 'decimal:2',
            'fee_due'                       => 'decimal:2',
            'fee_paid'                       => 'decimal:2',
            'paid_date'                     => 'date',
            'version'                        => 'integer',
            'superseded_at'                 => 'datetime',
            'accrued_at'                     => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function loanTransaction(): BelongsTo
    {
        return $this->belongsTo(LoanTransaction::class);
    }
}
