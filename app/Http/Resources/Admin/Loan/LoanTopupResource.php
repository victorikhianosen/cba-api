<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanTopupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'loan_id'                   => $this->loan_id,
            'loan'                     => $this->whenLoaded('loan', fn () => [
                'id'              => $this->loan->id,
                'account_number'  => $this->loan->account_number,
            ]),
            'reference'                => $this->reference,
            'topup_amount'             => $this->topup_amount,
            'principal_before'          => $this->principal_before,
            'principal_after'           => $this->principal_after,
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
            'old_schedule_version'       => $this->old_schedule_version,
            'new_schedule_version'       => $this->new_schedule_version,
            'created_at'               => $this->created_at,
        ];
    }
}
