<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    public function cashierSession(): BelongsTo
    {
        return $this->belongsTo(CashierSession::class);
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(Posting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Only set for a withdrawal request — the cashier who asked for it (may differ from
    // created_by if this is ever created on a cashier's behalf; today they're the same).
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // counterparty_id points at a Vault, a Teller, or an Account row depending on
    // counterparty_type — not a morph relation since it's just a display lookup, not
    // something posted through.
    public function counterparty(): ?Model
    {
        return match ($this->counterparty_type) {
            'vault'   => Vault::find($this->counterparty_id),
            'teller'  => Teller::find($this->counterparty_id),
            'account' => Account::find($this->counterparty_id),
            default   => null,
        };
    }
}
