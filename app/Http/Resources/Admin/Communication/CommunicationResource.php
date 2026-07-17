<?php

namespace App\Http\Resources\Admin\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'actor_type'     => $this->actor_type ? class_basename($this->actor_type) : null,
            'actor_id'       => $this->actor_id,
            'channel'        => $this->channel,
            'type'           => $this->type,
            'recipient'      => $this->recipient,
            'subject'        => $this->subject,
            'body'           => $this->body,
            'payload'        => $this->payload,
            'status'         => $this->status,
            'failure_reason' => $this->failure_reason,
            'sent_at'        => $this->sent_at,
            'delivered_at'   => $this->delivered_at,
            'created_at'     => $this->created_at,
        ];
    }
}
