<?php

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DirectorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'customer_id'       => $this->customer_id,
            'title'             => $this->title,
            'first_name'        => $this->first_name,
            'middle_name'       => $this->middle_name,
            'last_name'         => $this->last_name,
            'position'          => $this->position,
            'phone'             => $this->phone,
            'email'             => $this->email,
            'gender'            => $this->gender,
            'dob'               => $this->dob,
            'nationality'       => $this->nationality,
            'address'           => $this->address,
            'occupation'        => $this->occupation,
            'appointment_date'  => $this->appointment_date,
            'resignation_date'  => $this->resignation_date,
            'bvn'               => $this->bvn,
            'nin'               => $this->nin,
            'tin'               => $this->tin,
            'id_type'           => $this->id_type,
            'id_number'         => $this->id_number,
            'passport_photo'    => $this->passport_photo,
            'signature'         => $this->signature,
            'is_primary'        => $this->is_primary,
            'status'            => $this->status,
            'remarks'           => $this->remarks,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
