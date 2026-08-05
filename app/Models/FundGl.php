<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FundGl\FundGlStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FundGl extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:4',
            'status'      => FundGlStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class, 'source_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class, 'destination_id');
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
