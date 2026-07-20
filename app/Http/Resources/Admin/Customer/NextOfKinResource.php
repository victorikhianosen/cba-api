<?php

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NextOfKinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'customer_id'  => $this->customer_id,
            'title'        => $this->title,
            'first_name'   => $this->first_name,
            'middle_name'  => $this->middle_name,
            'last_name'    => $this->last_name,
            'relationship' => $this->relationship,
            'gender'       => $this->gender,
            'dob'          => $this->dob,
            'phone'        => $this->phone,
            'email'        => $this->email,
            'occupation'   => $this->occupation,
            'address'      => $this->address,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
