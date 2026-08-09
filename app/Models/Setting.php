<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'editable'  => 'boolean',
            'sensitive' => 'boolean',
            'encrypted' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Decrypts (when `encrypted`) then casts the raw stored string per the setting's `type` —
    // this is what callers actually want, not the raw persisted string.
    public function resolvedValue(): mixed
    {
        $value = $this->value;

        if ($value === null) {
            return null;
        }

        if ($this->encrypted) {
            $value = Crypt::decryptString($value);
        }

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'date'    => Carbon::parse($value),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
