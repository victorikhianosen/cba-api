<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Posting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'entry_group_id'  => $this->entry_group_id,
            'account_id'      => $this->account_id,
            'customer_id'     => $this->customer_id,
            'debit_credit'    => $this->debit_credit,
            'amount'          => $this->amount,
            'balance_before'  => $this->balance_before,
            'balance_after'   => $this->balance_after,
            'currency_id'     => $this->currency_id,
            'value_date'      => $this->value_date,
            'posting_date'    => $this->posting_date,
            'status'          => $this->status,
            'narration'       => $this->narration,
        ];
    }
}
