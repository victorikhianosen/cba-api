<?php

declare(strict_types=1);

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
            'transaction_limit'  => 'integer',
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
