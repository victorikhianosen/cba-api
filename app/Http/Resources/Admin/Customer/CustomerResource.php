<?php

namespace App\Http\Resources\Admin\Customer;

use App\Http\Resources\Admin\AccountOfficer\AccountOfficerResource;
use App\Http\Resources\Admin\Branch\BranchResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'cif_number'          => $this->cif_number,
            'branch_id'           => $this->branch_id,
            'branch'              => new BranchResource($this->whenLoaded('branch')),
            'account_officer_id'  => $this->account_officer_id,
            'account_officer'     => new AccountOfficerResource($this->whenLoaded('accountOfficer')),
            'customer_type'       => $this->customer_type,
            'guardian_id'         => $this->guardian_id,

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

            'is_staff' => $this->is_staff,
            'pep'      => $this->pep,

            'enable_internet_bank'  => $this->enable_internet_bank,
            'enable_sms'            => $this->enable_sms,
            'enable_email'          => $this->enable_email,
            'enable_reset_password' => $this->enable_reset_password,
            'enable_panic_password' => $this->enable_panic_password,

            'id_verified'      => $this->id_verified,
            'face_verified'    => $this->face_verified,
            'utility_verified' => $this->utility_verified,

            'mother_maiden_name' => $this->mother_maiden_name,
            'spouse_name'        => $this->spouse_name,

            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,

            'rejected_by'      => $this->rejected_by,
            'rejected_at'      => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,

            'closed_by'      => $this->closed_by,
            'closed_at'      => $this->closed_at,
            'closure_reason' => $this->closure_reason,

            'documents' => DocumentResource::collection($this->whenLoaded('documents')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
