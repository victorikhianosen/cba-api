<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanGuarantor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at'     => 'datetime',
            'claimed'         => 'boolean',
            'claimed_amount'  => 'decimal:2',
            'claimed_at'      => 'datetime',
            'released_at'     => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 'Jumia Ltd' for a corporate guarantee, 'Jumia Athens' for a personal one — the one
    // label every audit/notification message needs regardless of guarantee_type.
    public function getDisplayNameAttribute(): ?string
    {
        return $this->guarantee_type === 'corporate' ? $this->company_name : $this->full_name;
    }
}
