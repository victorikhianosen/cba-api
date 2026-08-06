<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'fee'           => 'decimal:2',
            'commission'    => 'decimal:2',
            'vat'           => 'decimal:2',
            'discount'      => 'decimal:2',
            'exchange_rate' => 'decimal:2',
            'is_reversed'   => 'boolean',
            'payload'       => 'array',
            'metadata'      => 'array',
            'processed_at'  => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(Posting::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'reversal_transaction_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
