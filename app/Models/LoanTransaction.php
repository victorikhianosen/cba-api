<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'                        => 'decimal:2',
            'outstanding_principal_after'   => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(Posting::class);
    }

    public function loanSchedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
