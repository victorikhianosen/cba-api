<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditTrail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'before_change' => 'array',
        'after_change'  => 'array',
    ];

    public function performer(): MorphTo
    {
        return $this->morphTo('performer', 'performed_by_type', 'performed_by_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit trails cannot be modified.'));
        static::deleting(fn () => throw new RuntimeException('Audit trails cannot be deleted.'));
    }
}