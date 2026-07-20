<?php

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'customer_id'          => $this->customer_id,
            'business_name'        => $this->business_name,
            'trading_name'         => $this->trading_name,
            'business_type'        => $this->business_type,
            'registration_number'  => $this->registration_number,
            'registration_date'    => $this->registration_date,
            'incorporation_date'   => $this->incorporation_date,
            'nature_of_business'   => $this->nature_of_business,
            'industry'             => $this->industry,
            'tin'                  => $this->tin,
            'vat_number'           => $this->vat_number,
            'business_phone'       => $this->business_phone,
            'business_email'       => $this->business_email,
            'website'              => $this->website,
            'annual_turnover'      => $this->annual_turnover,
            'monthly_turnover'     => $this->monthly_turnover,
            'number_of_employees'  => $this->number_of_employees,
            'source_of_funds'      => $this->source_of_funds,
            'status'               => $this->status,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
