<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountStatementTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
