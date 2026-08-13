<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                            => $this->id,
            'loan_id'                        => $this->loan_id,
            'loan_account_number'            => $this->loan_account_number,
            'type'                          => $this->type,
            'amount'                        => $this->amount,
            'outstanding_principal_before'  => $this->outstanding_principal_before,
            'outstanding_principal_after'   => $this->outstanding_principal_after,
            'posting_id'                     => $this->posting_id,
            'reference'                       => $this->reference,
            'transaction_reference'           => $this->transaction_reference,
            'loan_schedule_id'               => $this->loan_schedule_id,
            'description'                    => $this->description,
            'created_by'                      => $this->created_by,
            'created_by_name'                  => $this->whenLoaded('createdBy', fn () => $this->createdBy?->username),
            'created_at'                      => $this->created_at,
        ];
    }
}
