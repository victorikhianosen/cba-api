<?php

namespace App\Http\Resources\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'branch_id'     => $this->branch_id,
            'first_name'    => $this->first_name,
            'last_name'      => $this->last_name,
            'username'      => $this->username,
            'code'          => $this->code,
            'staff_code'    => $this->staff_code,
            'email'         => $this->email,
            'gender'        => $this->gender,
            'profile_picture' => $this->profile_picture ? Storage::disk('s3')->url($this->profile_picture) : null,
            'phone'         => $this->phone,
            'address'       => $this->address,
            'city'          => $this->city,
            'state'         => $this->state,
            'country'       => $this->country,
            'notes'         => $this->notes,
            'enable_2fa'    => $this->enable_2fa,
            'status'        => $this->status,
            'roles'         => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions'   => $this->whenLoaded('roles', fn () => $this->getAllPermissions()->pluck('name')),
            'last_login_at' => $this->last_login_at,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
