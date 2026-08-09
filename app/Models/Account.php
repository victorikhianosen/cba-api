<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_balance'     => 'decimal:2',
            'available_balance'   => 'decimal:2',
            'lien_balance'        => 'decimal:2',
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

    public function liens(): HasMany
    {
        return $this->hasMany(AccountLien::class);
    }

    // Set only when this account IS a virtual account — its own beneficiary/parent detail row.
    public function virtualAccountDetails(): HasOne
    {
        return $this->hasOne(VirtualAccountDetail::class);
    }

    // The inverse: when this account is used as a settlement parent, every VA sweeping into it.
    public function virtualAccounts(): HasMany
    {
        return $this->hasMany(VirtualAccountDetail::class, 'parent_account_id');
    }
}
