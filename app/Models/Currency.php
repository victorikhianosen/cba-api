<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'buy_rate'         => 'decimal:6',
            'sell_rate'        => 'decimal:6',
            'exchange_rate'    => 'decimal:6',
            'is_base_currency' => 'boolean',
        ];
    }
}
