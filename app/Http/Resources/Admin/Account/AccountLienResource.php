<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountLienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'reference'      => $this->reference,

            'account_id'     => $this->account_id,
            'account_number' => $this->account_number,

            'customer_id'    => $this->customer_id,
            'customer'       => $this->whenLoaded('customer', fn () => [
                'id'         => $this->customer->id,
                'cif_number' => $this->customer->cif_number,
                'full_name'  => trim("{$this->customer->first_name} {$this->customer->last_name}") ?: $this->customer->business_name,
            ]),

            'currency_id'    => $this->currency_id,
            'currency_code'  => $this->whenLoaded('currency', fn () => $this->currency?->code),

            'amount'         => $this->amount,
            'reason'         => $this->reason,
            'remarks'        => $this->remarks,
            'status'         => $this->status,

            'created_by'       => $this->created_by,
            'created_by_name'  => $this->whenLoaded('createdBy', fn () => $this->createdBy?->username),
            'approved_by'      => $this->approved_by,
            'approved_by_name' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->username),
            'rejected_by'      => $this->rejected_by,
            'rejected_by_name' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy?->username),
            'release_by'       => $this->release_by,
            'release_by_name'  => $this->whenLoaded('releaseBy', fn () => $this->releaseBy?->username),

            'approved_at'    => $this->approved_at,
            'rejected_at'    => $this->rejected_at,
            'released_at'    => $this->released_at,
            'expires_at'     => $this->expires_at,
            'release_reason' => $this->release_reason,

            'branch_id'      => $this->branch_id,

            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
