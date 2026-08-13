<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRefinanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'old_loan_id'               => $this->old_loan_id,
            'old_loan'                 => $this->whenLoaded('oldLoan', fn () => [
                'id'              => $this->oldLoan->id,
                'account_number'  => $this->oldLoan->account_number,
            ]),
            'new_loan_id'               => $this->new_loan_id,
            'new_loan'                 => $this->whenLoaded('newLoan', fn () => $this->newLoan ? [
                'id'              => $this->newLoan->id,
                'account_number'  => $this->newLoan->account_number,
            ] : null),
            'reference'                => $this->reference,
            'loan_product_id'           => $this->loan_product_id,
            'old_outstanding_amount'     => $this->old_outstanding_amount,
            'additional_cash_out'        => $this->additional_cash_out,
            'new_principal_amount'       => $this->new_principal_amount,
            'new_duration'   => $this->new_duration,
            'new_interest_rate'          => $this->new_interest_rate,
            'reason'                   => $this->reason,
            'status'                   => $this->status,
            'requested_by'               => $this->requested_by,
            'requested_by_name'           => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->username),
            'approved_by'               => $this->approved_by,
            'approved_by_name'           => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->username),
            'approved_at'               => $this->approved_at,
            'rejected_by'               => $this->rejected_by,
            'rejected_at'               => $this->rejected_at,
            'rejection_reason'          => $this->rejection_reason,
            'created_at'               => $this->created_at,
        ];
    }
}
