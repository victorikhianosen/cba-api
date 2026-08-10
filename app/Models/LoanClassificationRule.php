<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanClassificationRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_days_overdue' => 'integer',
            'max_days_overdue' => 'integer',
            'sort_order'       => 'integer',
        ];
    }
}
