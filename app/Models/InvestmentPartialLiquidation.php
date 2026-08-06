<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Investment\InvestmentPartialLiquidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentPartialLiquidation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'status'      => InvestmentPartialLiquidationStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function investmentTransaction(): BelongsTo
    {
        return $this->belongsTo(InvestmentTransaction::class);
    }
}
