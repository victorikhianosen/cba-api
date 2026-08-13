<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRecoveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'loan_id'                => $this->loan_id,
            'amount'                 => $this->amount,
            'principal_portion'      => $this->principal_portion,
            'interest_portion'       => $this->interest_portion,
            'penalty_portion'        => $this->penalty_portion,
            'fee_portion'            => $this->fee_portion,
            'channel'                => $this->channel,
            'reference'              => $this->reference,
            'recovery_officer_id'     => $this->recovery_officer_id,
            'recovered_at'           => $this->recovered_at,
            'funding_account_id'      => $this->funding_account_id,
            'notes'                  => $this->notes,
            'created_at'             => $this->created_at,
        ];
    }
}
