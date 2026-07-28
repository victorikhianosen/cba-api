<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Minimal customer projection for lookups (e.g. resolving a guardian by CIF
// number on the minor onboarding form) — not the full CustomerResource.
class CustomerLookupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'cif_number'    => $this->cif_number,
            'customer_type' => $this->customer_type,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'business_name' => $this->business_name,
            'status'        => $this->status,
        ];
    }
}
