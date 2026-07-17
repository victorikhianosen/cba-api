<?php

namespace App\Http\Resources\Admin\AccountOfficer;

use App\Http\Resources\Admin\Branch\BranchResource;
use App\Http\Resources\Admin\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountOfficerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'branch_id'  => $this->branch_id,
            'branch'     => new BranchResource($this->whenLoaded('branch')),
            'user_id'    => $this->user_id,
            'user'       => new UserResource($this->whenLoaded('user')),
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'gender'     => $this->gender,
            'code'       => $this->code,
            'address'    => $this->address,
            'city'       => $this->city,
            'state'      => $this->state,
            'country'    => $this->country,
            'status'     => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
