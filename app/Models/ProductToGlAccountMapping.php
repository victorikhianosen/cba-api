<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductToGlAccountMapping extends Model
{
    protected $guarded = [];

    public function product(): MorphTo
    {
        return $this->morphTo();
    }

    public function generalLedger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class);
    }
}
