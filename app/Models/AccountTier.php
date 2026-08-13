<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'daily_transaction_limit'       => 'integer',
            'daily_withdrawal_limit'        => 'integer',
            'daily_transfer_limit'          => 'integer',
            'max_account_balance'           => 'integer',
            'max_single_transaction_amount' => 'integer',
            'max_daily_inflow'              => 'integer',
            'max_daily_outflow'             => 'integer',
            'require_bvn'                    => 'boolean',
            'require_nin'                    => 'boolean',
            'require_address_verification'   => 'boolean',
            'require_face_verification'      => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
