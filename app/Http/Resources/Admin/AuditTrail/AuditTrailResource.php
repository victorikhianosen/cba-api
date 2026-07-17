<?php

namespace App\Http\Resources\Admin\AuditTrail;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditTrailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'performed_by_type' => $this->performed_by_type,
            'performed_by_id'   => $this->performed_by_id,
            'performed_by_name' => $this->performed_by_name,
            'module'            => $this->module,
            'action'            => $this->actions,
            'description'       => $this->description,
            'before_change'     => $this->before_change,
            'after_change'      => $this->after_change,
            'ip'                => $this->ip,
            'agent'             => $this->agent,
            'channel'           => $this->channel,
            'created_at'        => $this->created_at,
        ];
    }
}
