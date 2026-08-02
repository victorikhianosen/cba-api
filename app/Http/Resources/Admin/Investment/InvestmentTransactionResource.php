<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Investment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'investment_id'            => $this->investment_id,
            'type'                     => $this->type,
            'amount'                   => $this->amount,
            'principal_balance_after'  => $this->principal_balance_after,
            'posting_id'               => $this->posting_id,
            'posting_reference'        => $this->whenLoaded('posting', fn () => $this->posting?->reference),
            'transaction_reference'    => $this->whenLoaded('posting', fn () => $this->posting?->transaction_reference),
            'investment_schedule_id'   => $this->investment_schedule_id,
            'description'              => $this->description,
            'created_by'               => $this->whenLoaded('createdBy', fn () => $this->createdBy?->username),
            'created_at'               => $this->created_at,
        ];
    }
}
