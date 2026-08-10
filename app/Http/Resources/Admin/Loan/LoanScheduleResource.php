<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'installment_number'          => $this->installment_number,
            'due_date'                    => $this->due_date,
            'opening_principal_balance'   => $this->opening_principal_balance,
            'principal_due'                => $this->principal_due,
            'interest_due'                 => $this->interest_due,
            'closing_principal_balance'   => $this->closing_principal_balance,
            'principal_paid'                => $this->principal_paid,
            'interest_paid'                 => $this->interest_paid,
            'penalty_due'                   => $this->penalty_due,
            'penalty_paid'                  => $this->penalty_paid,
            'fee_due'                       => $this->fee_due,
            'fee_paid'                       => $this->fee_paid,
            'paid_date'                     => $this->paid_date,
            'status'                        => $this->status,
            'version'                       => $this->version,
        ];
    }
}
