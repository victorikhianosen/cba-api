<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Communication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload'      => 'array',
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
