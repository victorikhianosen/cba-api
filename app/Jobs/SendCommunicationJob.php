<?php

namespace App\Jobs;

use App\Services\Communication\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $actorType,
        public int $actorId,
        public string $channel,
        public string $recipient,
        public string $body,
        public ?string $subject,
        public string $type,
    ) {}

    public function handle(CommunicationService $communications): void
    {
        $actor = $this->actorType::find($this->actorId);

        if (! $actor) {
            return;
        }

        $communications->send(
            actor: $actor,
            channel: $this->channel,
            recipient: $this->recipient,
            body: $this->body,
            subject: $this->subject,
            type: $this->type,
        );
    }
}
