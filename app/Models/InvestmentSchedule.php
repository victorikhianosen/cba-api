<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date'                   => 'date',
            'opening_principal_balance'  => 'decimal:6',
            'expected_interest_amount'   => 'decimal:6',
            'closing_principal_balance'  => 'decimal:6',
            'paid_amount'                => 'decimal:6',
            'paid_date'                  => 'date',
            'accrued_at'                 => 'datetime',
            'version'                    => 'integer',
            'superseded_at'              => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function investmentTransaction(): BelongsTo
    {
        return $this->belongsTo(InvestmentTransaction::class);
    }
}
