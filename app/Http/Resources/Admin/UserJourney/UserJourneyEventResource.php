<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\UserJourney;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserJourneyEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'session_id'        => $this->session_id,
            'performed_by_id'   => $this->performed_by_id,
            'performed_by_name' => $this->performed_by_name,
            'event_type'        => $this->event_type,
            'action'            => $this->action,
            'module'            => $this->module,
            'method'            => $this->method,
            'route'             => $this->route,
            'url'               => $this->url,
            'description'       => $this->description,
            'metadata'          => $this->metadata,
            'ip'                => $this->ip,
            'agent'             => $this->agent,
            'occurred_at'       => $this->occurred_at,
        ];
    }
}
