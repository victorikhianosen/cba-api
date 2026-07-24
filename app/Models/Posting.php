<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Posting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:4',
            'exchange_rate'  => 'decimal:8',
            'fee'            => 'decimal:4',
            'vat'            => 'decimal:4',
            'tax'            => 'decimal:4',
            'total_amount'   => 'decimal:4',
            'payload'        => 'array',
            'metadata'       => 'array',
            'reviewed_at'    => 'datetime',
            'approved_at'    => 'datetime',
            'rejected_at'    => 'datetime',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function financialTransaction(): HasOne
    {
        return $this->hasOne(FinancialTransaction::class);
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

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
