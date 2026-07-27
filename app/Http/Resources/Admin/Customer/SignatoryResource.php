<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SignatoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'customer_id'         => $this->customer_id,
            'director_id'         => $this->director_id,
            'title'               => $this->title,
            'first_name'          => $this->first_name,
            'middle_name'         => $this->middle_name,
            'last_name'           => $this->last_name,
            'position'            => $this->position,
            'phone'               => $this->phone,
            'email'               => $this->email,
            'signature'           => $this->signature,
            'signature_url'       => $this->signature ? Storage::disk('s3')->url($this->signature) : null,
            'passport_photo'      => $this->passport_photo,
            'passport_photo_url'  => $this->passport_photo ? Storage::disk('s3')->url($this->passport_photo) : null,
            'gender'             => $this->gender,
            'dob'                => $this->dob,
            'bvn'                => $this->bvn,
            'nin'                => $this->nin,
            'address'            => $this->address,
            'status'             => $this->status,
            'remarks'            => $this->remarks,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
