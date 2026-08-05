<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\User;
use App\Notifications\GenericAlertNotification;
use Illuminate\Support\Facades\Notification;

// The single reusable entry point for firing an in-app + live (Reverb) notification —
// any service call site just calls one of these two methods, the same way every service
// already calls AuditService::log() for the audit trail. Queued via
// GenericAlertNotification's ShouldQueue, so this never blocks the request.
class NotificationService
{
    // Notifies every user holding $permission — e.g. "a new lien needs approval",
    // sent to everyone who can act on it.
    public function notifyPermission(string $permission, string $module, int|string $subjectId, string $title, string $body): void
    {
        $users = User::permission($permission)->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new GenericAlertNotification($module, $subjectId, $title, $body));
    }

    // Notifies a single user — e.g. "your lien request was approved", sent to whoever
    // requested it.
    public function notifyUser(?User $user, string $module, int|string $subjectId, string $title, string $body): void
    {
        if (! $user) {
            return;
        }

        $user->notify(new GenericAlertNotification($module, $subjectId, $title, $body));
    }
}
