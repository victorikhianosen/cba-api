<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerBulkImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'batch_id'      => $this->batch_id,
            'row_number'    => $this->row_number,
            'status'        => $this->status,
            'row_data'      => $this->row_data,
            'error_reason'  => $this->error_reason,
            'customer_id'   => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => trim("{$this->customer?->first_name} {$this->customer?->last_name}") ?: $this->customer?->business?->business_name),
            'cif_number'    => $this->whenLoaded('customer', fn () => $this->customer?->cif_number),
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->username),
            'created_at'    => $this->created_at,
        ];
    }
}
