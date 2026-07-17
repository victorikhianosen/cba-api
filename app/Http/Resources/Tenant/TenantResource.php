<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'name'         => $this->name,
            'type'         => $this->type,

            'email'        => $this->email,
            'phone'        => $this->phone,
            'website'      => $this->website,

            'country'      => $this->country,
            'state'        => $this->state,
            'city'         => $this->city,
            'address'      => $this->address,

            'timezone'     => $this->timezone,
            'currency'     => $this->currency,
            'locale'       => $this->locale,

            'status'    => $this->status,
            'activated_at' => $this->activated_at,
            'created_at'   => $this->created_at,

            // NOTE: database_name / username / password / host are intentionally
            // omitted — they are internal infrastructure and must never be exposed.
        ];
    }
}