<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Director extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob'               => 'date',
            'appointment_date'  => 'date',
            'resignation_date'  => 'date',
            'is_primary'        => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function signatory(): HasOne
    {
        return $this->hasOne(Signatory::class);
    }
}
