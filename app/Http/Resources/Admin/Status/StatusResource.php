<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Status;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'category'    => $this->category,
            'description' => $this->description,
        ];
    }
}
