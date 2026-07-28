<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Account;

use App\Http\Resources\Admin\AccountProduct\AccountProductResource;
use App\Http\Resources\Admin\AccountTier\AccountTierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'customer_id'         => $this->customer_id,
            'customer'            => $this->whenLoaded('customer', fn () => [
                'id'            => $this->customer->id,
                'cif_number'    => $this->customer->cif_number,
                'customer_type' => $this->customer->customer_type,
                'full_name'     => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
                'email'         => $this->customer->email,
                'phone'         => $this->customer->phone,
            ]),
            'account_product_id'  => $this->account_product_id,
            'account_product'     => new AccountProductResource($this->whenLoaded('accountProduct')),
            'currency_id'         => $this->currency_id,
            'currency_code'       => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'account_tier_id'     => $this->account_tier_id,
            'account_tier'        => new AccountTierResource($this->whenLoaded('accountTier')),

            'account_name'      => $this->account_name,
            'account_number'    => $this->account_number,
            'primary_account'   => $this->primary_account,
            'account_balance'   => $this->account_balance,
            'available_balance' => $this->available_balance,
            'lien_balance'      => $this->lien_balance,

            'status'  => $this->status,
            'pnd'     => $this->pnd,
            'pnc'     => $this->pnc,
            'channel' => $this->channel,

            'last_transaction_at' => $this->last_transaction_at,

            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
