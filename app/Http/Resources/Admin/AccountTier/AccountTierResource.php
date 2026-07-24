<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\AccountTier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'level'       => $this->level,
            'description' => $this->description,
            'status'      => $this->status,

            'daily_transaction_limit'       => $this->daily_transaction_limit,
            'daily_withdrawal_limit'        => $this->daily_withdrawal_limit,
            'daily_transfer_limit'          => $this->daily_transfer_limit,
            'max_account_balance'           => $this->max_account_balance,
            'max_single_transaction_amount' => $this->max_single_transaction_amount,
            'max_daily_inflow'              => $this->max_daily_inflow,
            'max_daily_outflow'             => $this->max_daily_outflow,

            'require_bvn'                   => $this->require_bvn,
            'require_nin'                   => $this->require_nin,
            'require_address_verification'  => $this->require_address_verification,
            'require_face_verification'     => $this->require_face_verification,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
