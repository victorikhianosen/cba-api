<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use App\Services\Storage\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanCollateralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'loan_id'                      => $this->loan_id,
            'type'                        => $this->type,
            'description'                  => $this->description,
            'estimated_amount'            => $this->estimated_amount,
            'proof_of_ownership_path'      => $this->proof_of_ownership_path,
            'proof_of_ownership_url'      => app(FileStorageService::class)->url($this->proof_of_ownership_path),
            'status'                       => $this->status,
            'released_at'                  => $this->released_at,
            'release_reason'               => $this->release_reason,
            'realized_at'                  => $this->realized_at,
            'realized_amount'              => $this->realized_amount,
            'created_at'                   => $this->created_at,
        ];
    }
}
