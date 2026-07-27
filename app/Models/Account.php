<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_balance'     => 'decimal:6',
            'available_balance'   => 'decimal:6',
            'lien_balance'        => 'decimal:6',
            'pnd'                 => 'boolean',
            'pnc'                 => 'boolean',
            'primary_account'     => 'boolean',
            'last_transaction_at' => 'datetime',
            'approved_at'         => 'datetime',
            'rejected_at'         => 'datetime',
            'closed_at'           => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function accountProduct(): BelongsTo
    {
        return $this->belongsTo(AccountProduct::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function accountTier(): BelongsTo
    {
        return $this->belongsTo(AccountTier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
