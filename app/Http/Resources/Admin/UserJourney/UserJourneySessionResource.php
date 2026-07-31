<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\UserJourney;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One row per session — the summarized aggregate produced by UserJourneyService::sessions().
class UserJourneySessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'session_id'        => $this->session_id,
            'performed_by_id'   => $this->performed_by_id,
            'performed_by_name' => $this->performed_by_name,
            'started_at'        => $this->started_at,
            'ended_at'          => $this->ended_at,
            'last_activity_at'  => $this->last_activity_at,
            'timed_out'         => (bool) $this->timed_out,
            'event_count'       => (int) $this->event_count,
            'ip'                => $this->ip,
        ];
    }
}
