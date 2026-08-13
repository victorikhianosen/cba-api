<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'loan_id'                   => $this->loan_id,
            'loan'                     => $this->whenLoaded('loan', fn () => [
                'id'              => $this->loan->id,
                'account_number'  => $this->loan->account_number,
                'customer'        => $this->loan->relationLoaded('customer') ? [
                    'full_name' => trim("{$this->loan->customer?->first_name} {$this->loan->customer?->last_name}") ?: $this->loan->customer?->business_name,
                ] : null,
            ]),
            'collection_officer_id'      => $this->collection_officer_id,
            'days_past_due'             => $this->days_past_due,
            'contact_method'            => $this->contact_method,
            'outcome'                   => $this->outcome,
            'promise_date'              => $this->promise_date,
            'promise_amount'            => $this->promise_amount,
            'promise_kept'              => $this->promise_kept,
            'escalated'                 => $this->escalated,
            'escalation_level'          => $this->escalation_level,
            'legal_action_initiated'     => $this->legal_action_initiated,
            'notes'                     => $this->notes,
            'created_by'                => $this->created_by,
            'created_by_name'            => $this->whenLoaded('createdBy', fn () => $this->createdBy?->username),
            'created_at'                => $this->created_at,
        ];
    }
}
