<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanGuarantorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                              => $this->id,
            'loan_id'                          => $this->loan_id,
            'guarantee_type'                  => $this->guarantee_type,
            'customer_id'                      => $this->customer_id,
            'display_name'                    => $this->display_name,

            // Personal guarantee.
            'full_name'                        => $this->full_name,
            'email'                            => $this->email,
            'phone'                            => $this->phone,
            'role_in_business'                => $this->role_in_business,
            'id_type'                          => $this->id_type,
            'valid_id_path'                    => $this->valid_id_path,
            'valid_id_url'                    => app(FileStorageService::class)->url($this->valid_id_path),

            // Corporate guarantee.
            'company_name'                    => $this->company_name,
            'rc_number'                        => $this->rc_number,
            'tax_identification_number'      => $this->tax_identification_number,
            'business_address'                => $this->business_address,
            'director_name'                    => $this->director_name,
            'director_email'                  => $this->director_email,

            'signed_guarantee_letter_path'    => $this->signed_guarantee_letter_path,
            'signed_guarantee_letter_url'    => app(FileStorageService::class)->url($this->signed_guarantee_letter_path),

            'status'                           => $this->status,
            'approved_by'                      => $this->approved_by,
            'approved_at'                      => $this->approved_at,
            'claimed'                          => $this->claimed,
            'claimed_amount'                  => $this->claimed_amount,
            'claimed_at'                      => $this->claimed_at,
            'released_at'                      => $this->released_at,
            'created_at'                       => $this->created_at,
        ];
    }
}
