<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanRepaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'loan_id'              => $this->loan_id,
            'loan'                => $this->whenLoaded('loan', fn () => [
                'id'              => $this->loan->id,
                'account_number'  => $this->loan->account_number,
            ]),
            'reference'                => $this->reference,
            'amount'                   => $this->amount,
            'funding_account_id'        => $this->funding_account_id,
            'funding_account'           => $this->whenLoaded('fundingAccount', fn () => $this->fundingAccount ? [
                'id'              => $this->fundingAccount->id,
                'account_number'  => $this->fundingAccount->account_number,
                'account_name'    => $this->fundingAccount->account_name,
            ] : null),
            'description'              => $this->description,
            // Only populated once approved — the individual penalty/fee/interest/principal
            // (and possible overpayment) GL-posted legs this repayment produced, so the
            // requester/checker can see exactly what was booked, not just the total amount.
            'transactions'             => $this->whenLoaded('loanTransactions', fn () => $this->loanTransactions->map(fn ($transaction) => [
                'id'                     => $transaction->id,
                'type'                   => $transaction->type,
                'amount'                 => $transaction->amount,
                'reference'              => $transaction->reference,
                'transaction_reference'  => $transaction->transaction_reference,
            ])->all()),
            'status'              => $this->status,
            'requested_by'         => $this->requested_by,
            'requested_by_name'     => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->username),
            'approved_by'          => $this->approved_by,
            'approved_by_name'      => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->username),
            'approved_at'          => $this->approved_at,
            'rejected_by'          => $this->rejected_by,
            'rejected_at'          => $this->rejected_at,
            'rejection_reason'     => $this->rejection_reason,
            'created_at'          => $this->created_at,
        ];
    }
}
