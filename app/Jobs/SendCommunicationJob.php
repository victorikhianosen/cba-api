<?php

declare(strict_types=1);

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

    /**
     * @param string[] $filePaths Shared across every recipient in a bulk send — not
     *   deleted by any individual job, since other jobs for the same batch may still
     *   be pending (same reasoning as sendEmailWithAttachments()'s callers, just at
     *   batch scope instead of per-request scope).
     * @param array<string, string> $fileNames
     */
    public function __construct(
        public string $actorType,
        public int $actorId,
        public string $channel,
        public string $recipient,
        public string $body,
        public ?string $subject,
        public string $type,
        public array $filePaths = [],
        public array $fileNames = [],
    ) {}

    public function handle(CommunicationService $communications): void
    {
        $actor = $this->actorType::find($this->actorId);

        if (! $actor) {
            return;
        }

        if ($this->filePaths) {
            $communications->sendEmailWithAttachments(
                actor: $actor,
                type: $this->type,
                recipient: $this->recipient,
                body: $this->body,
                subject: (string) $this->subject,
                filePaths: $this->filePaths,
                fileNames: $this->fileNames,
            );

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
