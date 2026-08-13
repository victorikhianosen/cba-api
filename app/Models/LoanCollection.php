<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCollection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'days_past_due'            => 'integer',
            'promise_date'             => 'date',
            'promise_amount'           => 'decimal:2',
            'promise_kept'             => 'boolean',
            'escalated'                => 'boolean',
            'legal_action_initiated'    => 'boolean',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function collectionOfficer(): BelongsTo
    {
        return $this->belongsTo(AccountOfficer::class, 'collection_officer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
