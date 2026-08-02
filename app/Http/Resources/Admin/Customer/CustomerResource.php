<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Customer;

use App\Enums\Customer\CustomerType;
use App\Http\Resources\Admin\Account\AccountResource;
use App\Http\Resources\Admin\AccountOfficer\AccountOfficerResource;
use App\Http\Resources\Admin\Branch\BranchResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer' => [
                'id'           => $this->id,
                'cif_number'   => $this->cif_number,
                'customer_type' => $this->customer_type,
                'legal_form'   => $this->legal_form,
                'guardian_id'  => $this->guardian_id,
                'status'       => $this->status,

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

                'bvn'        => $this->bvn,
                'nin_number' => $this->nin_number,
                'tin'        => $this->tin,
                'rc_number'  => $this->rc_number,

                'mother_maiden_name' => $this->mother_maiden_name,
                'spouse_name'        => $this->spouse_name,

                'is_staff' => $this->is_staff,
                'pep'      => $this->pep,

                'preferences' => [
                    'enable_internet_bank'  => $this->enable_internet_bank,
                    'enable_sms'            => $this->enable_sms,
                    'enable_email'          => $this->enable_email,
                    'enable_reset_password' => $this->enable_reset_password,
                    'enable_panic_password' => $this->enable_panic_password,
                ],

                'verification' => [
                    'id_verified'      => $this->id_verified,
                    'face_verified'    => $this->face_verified,
                    'utility_verified' => $this->utility_verified,
                    'otp_verified'     => $this->otp_verified,
                ],

                'device_token' => $this->device_token,
                'device_id'    => $this->device_id,

                // Arbitrary custom fields captured at onboarding: [{"key", "label", "value"}, ...].
                'metadata' => $this->metadata,

                'approval' => [
                    'approved_by' => $this->approved_by,
                    'approver'    => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->approvedBy)),
                    'approved_at' => $this->approved_at,

                    'rejected_by'      => $this->rejected_by,
                    'rejected_at'      => $this->rejected_at,
                    'rejection_reason' => $this->rejection_reason,

                    'closed_by'      => $this->closed_by,
                    'closed_at'      => $this->closed_at,
                    'closure_reason' => $this->closure_reason,
                ],

                'created_by' => $this->created_by,
                'creator'    => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->createdBy)),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],

            'branch'          => new BranchResource($this->whenLoaded('branch')),
            'account_officer' => new AccountOfficerResource($this->whenLoaded('accountOfficer')),
            'accounts'        => AccountResource::collection($this->whenLoaded('accounts')),
            'documents'       => DocumentResource::collection($this->whenLoaded('documents')),

            'address'         => new AddressResource($this->whenLoaded('currentAddress')),
            'address_history' => AddressResource::collection($this->whenLoaded('addresses')),

            'next_of_kin' => $this->when(
                ! in_array($this->customer_type, CustomerType::businessLike(), true),
                fn () => NextOfKinResource::collection($this->whenLoaded('nextOfKin')),
            ),

            'business'    => $this->when(in_array($this->customer_type, CustomerType::businessLike(), true), fn () => new BusinessResource($this->whenLoaded('business'))),
            // Directors aren't tied to a "corporate" type anymore — any business-like
            // customer may or may not have director rows on file.
            'directors'   => $this->when(in_array($this->customer_type, CustomerType::businessLike(), true), fn () => DirectorResource::collection($this->whenLoaded('directors'))),
            'signatories' => $this->when(in_array($this->customer_type, CustomerType::businessLike(), true), fn () => SignatoryResource::collection($this->whenLoaded('signatories'))),
        ];
    }

    // Minimal actor projection for created_by/approved_by — null-safe since the
    // relation may be loaded but empty (e.g. no admin ever set on legacy rows).
    private function userSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'username'   => $user->username,
        ];
    }
}
