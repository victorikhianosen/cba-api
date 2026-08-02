<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Investment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestmentScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'investment_id'              => $this->investment_id,
            'installment_number'         => $this->installment_number,
            'version'                    => $this->version,
            'superseded_at'              => $this->superseded_at,
            'due_date'                   => $this->due_date,
            'opening_principal_balance'  => $this->opening_principal_balance,
            'expected_interest_amount'   => $this->expected_interest_amount,
            'closing_principal_balance'  => $this->closing_principal_balance,
            'paid_amount'                => $this->paid_amount,
            'paid_date'                  => $this->paid_date,
            'status'                     => $this->status,
        ];
    }
}
