<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Teller\CashierSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Teller extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_float_ceiling_amount'         => 'decimal:6',
            'variance_tolerance_amount'             => 'decimal:6',
            'requires_variance_approval'            => 'boolean',
            'allows_teller_to_teller_transfer'      => 'boolean',
            'approved_at'                           => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function generalLedger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class);
    }

    // The expense-classified suspense GL used exclusively for customer-facing
    // deposit/withdrawal — separate from generalLedger() (the liability control GL used
    // for vault/teller-to-teller transfers) because the two need opposite polarity.
    public function customerCashGeneralLedger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class, 'customer_cash_general_ledger_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CashierAssignment::class);
    }

    // The teller's currently active cashier assignment, if any (unassigned_at is null).
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(CashierAssignment::class)->whereNull('unassigned_at')->latestOfMany();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashierSession::class);
    }

    // The teller's currently open (or reconciling) session, if any.
    public function currentSession(): HasOne
    {
        return $this->hasOne(CashierSession::class)
            ->whereIn('status', [
                CashierSessionStatus::OPEN->value,
                CashierSessionStatus::PENDING_RECONCILIATION->value,
                CashierSessionStatus::PENDING_VARIANCE_APPROVAL->value,
            ])
            ->latestOfMany();
    }
}
