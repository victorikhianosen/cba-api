<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signatory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob'                => 'date',
            'transaction_limit'  => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function director(): BelongsTo
    {
        return $this->belongsTo(Director::class);
    }
}
