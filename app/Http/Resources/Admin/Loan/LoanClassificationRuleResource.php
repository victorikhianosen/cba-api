<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Loan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanClassificationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'label'             => $this->label,
            'min_days_overdue'  => $this->min_days_overdue,
            'max_days_overdue'  => $this->max_days_overdue,
            'sort_order'        => $this->sort_order,
            'color'             => $this->color,
            'status'            => $this->status,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
