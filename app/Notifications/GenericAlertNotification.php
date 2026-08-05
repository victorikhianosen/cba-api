<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

// The single reusable notification type for the whole app — any feature that needs to
// alert a user (in-app bell + live push) constructs one of these rather than a bespoke
// notification class per module. $module + $subjectId are an opaque pointer the frontend
// resolves to a URL (e.g. module "lien" + subjectId 12 -> /customer-management/liens/12);
// this class doesn't know or care about frontend routes.
class GenericAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $module,
        private readonly int|string $subjectId,
        private readonly string $title,
        private readonly string $body,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'module'     => $this->module,
            'subject_id' => $this->subjectId,
            'title'      => $this->title,
            'body'       => $this->body,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
