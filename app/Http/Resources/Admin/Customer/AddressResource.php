<?php

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'customer_id' => $this->customer_id,
            'type'        => $this->type,
            'address'     => $this->address,
            'lgs'         => $this->lgs,
            'city'        => $this->city,
            'state'       => $this->state,
            'country'     => $this->country,
            'landmark'    => $this->landmark,
            'longitude'   => $this->longitude,
            'latitude'    => $this->latitude,
            'is_primary'  => $this->is_primary,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
