<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VirtualAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->whenLoaded('virtualAccountDetails');

        return [
            'id'              => $this->id,
            'account_number'  => $this->account_number,
            'account_name'    => $this->account_name,
            'status'          => $this->status,
            'currency_id'     => $this->currency_id,
            'currency_code'   => $this->whenLoaded('currency', fn () => $this->currency?->code),

            'customer_id'     => $this->customer_id,
            'customer'        => $this->whenLoaded('customer', fn () => [
                'id'            => $this->customer->id,
                'cif_number'    => $this->customer->cif_number,
                'full_name'     => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
            ]),

            'parent_account'  => $details && $details->relationLoaded('parentAccount') ? [
                'id'              => $details->parentAccount->id,
                'account_number'  => $details->parentAccount->account_number,
                'account_name'    => $details->parentAccount->account_name,
                'account_balance' => $details->parentAccount->account_balance,
            ] : null,

            'beneficiary' => $details ? [
                'name'  => $details->beneficiary_name,
                'email' => $details->beneficiary_email,
                'phone' => $details->beneficiary_phone,
                'bvn'   => $details->beneficiary_bvn,
            ] : null,

            'reference'   => $details?->reference,
            'provider'    => $details?->provider,
            'purpose'     => $details?->purpose,
            'expires_at'  => $details?->expires_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
