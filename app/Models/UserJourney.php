<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class UserJourney extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    public function performer(): MorphTo
    {
        return $this->morphTo('performer', 'performed_by_type', 'performed_by_id');
    }

    // Same immutability guarantee as AuditTrail — a journey is a record of what
    // happened, never something later edited.
    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('User journey entries cannot be modified.'));
        static::deleting(fn () => throw new RuntimeException('User journey entries cannot be deleted.'));
    }
}
