<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralLedger extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'balance'                        => 'decimal:6',
            'manual_journal_entries_allowed' => 'boolean',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GeneralLedger::class, 'parent_id');
    }
}
