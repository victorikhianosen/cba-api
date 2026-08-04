<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\CashierSession;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashierSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'status'                    => $this->status,

            'teller_id'                 => $this->teller_id,
            'teller_name'               => $this->whenLoaded('teller', fn () => $this->teller?->name),
            'teller_code'               => $this->whenLoaded('teller', fn () => $this->teller?->code),

            'cashier_id'                => $this->cashier_id,
            'cashier_name'              => $this->whenLoaded('cashier', fn () => trim(($this->cashier?->first_name ?? '') . ' ' . ($this->cashier?->last_name ?? '')) ?: $this->cashier?->username),

            'opening_float_amount'      => $this->opening_float_amount,
            'expected_closing_balance'  => $this->expected_closing_balance,
            'counted_closing_amount'    => $this->counted_closing_amount,
            'variance_amount'           => $this->variance_amount,
            'variance_reason'           => $this->variance_reason,

            'opened_at'                 => $this->opened_at,
            'closed_at'                 => $this->closed_at,
            'reconciled_at'             => $this->reconciled_at,

            'opened_by'                 => $this->opened_by,
            'closed_by'                 => $this->closed_by,
            'reconciled_by'             => $this->reconciled_by,
            'variance_approved_by'      => $this->variance_approved_by,
            'variance_approved_at'      => $this->variance_approved_at,

            'opening_posting_id'        => $this->opening_posting_id,
            'closing_posting_id'        => $this->closing_posting_id,

            'transactions' => CashierTransactionResource::collection($this->whenLoaded('transactions')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
