<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'cif_number'         => $this->cif_number,
            'branch_id'          => $this->branch_id,
            'account_officer_id' => $this->account_officer_id,
            'customer_type'      => $this->customer_type,
            'legal_form'         => $this->legal_form,
            'guardian_id'        => $this->guardian_id,

            'title'         => $this->title,
            'first_name'    => $this->first_name,
            'middle_name'   => $this->middle_name,
            'last_name'     => $this->last_name,
            'business_name' => $this->business_name,

            'phone'    => $this->phone,
            'email'    => $this->email,
            'username' => $this->username,

            'marital_status' => $this->marital_status,
            'gender'         => $this->gender,
            'dob'            => $this->dob,

            'occupation'     => $this->occupation,
            'working_status' => $this->working_status,
            'referral_code'  => $this->referral_code,

            'status' => $this->status,

            'bvn'        => $this->bvn,
            'nin_number' => $this->nin_number,
            'tin'        => $this->tin,
            'rc_number'  => $this->rc_number,

            'is_staff' => $this->is_staff,
            'pep'      => $this->pep,

            'enable_internet_bank'  => $this->enable_internet_bank,
            'enable_sms'            => $this->enable_sms,
            'enable_email'          => $this->enable_email,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
