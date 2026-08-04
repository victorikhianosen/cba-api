<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_float_amount'     => 'decimal:6',
            'expected_closing_balance' => 'decimal:6',
            'counted_closing_amount'   => 'decimal:6',
            'variance_amount'          => 'decimal:6',
            'opened_at'                => 'datetime',
            'closed_at'                => 'datetime',
            'reconciled_at'            => 'datetime',
            'variance_approved_at'     => 'datetime',
        ];
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(Teller::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function varianceApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'variance_approved_by');
    }

    public function openingPosting(): BelongsTo
    {
        return $this->belongsTo(Posting::class, 'opening_posting_id');
    }

    public function closingPosting(): BelongsTo
    {
        return $this->belongsTo(Posting::class, 'closing_posting_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashierTransaction::class);
    }
}
