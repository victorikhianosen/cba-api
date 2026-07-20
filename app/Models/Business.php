<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Business extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'registration_date'  => 'date',
            'incorporation_date' => 'date',
            'annual_turnover'    => 'decimal:2',
            'monthly_turnover'   => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
