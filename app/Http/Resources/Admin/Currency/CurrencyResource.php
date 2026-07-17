<?php

namespace App\Http\Resources\Admin\Currency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'name'             => $this->name,
            'symbol'           => $this->symbol,
            'buy_rate'         => $this->buy_rate,
            'sell_rate'        => $this->sell_rate,
            'exchange_rate'    => $this->exchange_rate,
            'is_base_currency' => $this->is_base_currency,
            'status'           => $this->status,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
