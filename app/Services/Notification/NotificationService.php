<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\GenericAlertNotification;
use App\Services\Communication\CommunicationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

// The single reusable entry point for firing an in-app + live (Reverb) notification —
// any service call site just calls one of these two methods, the same way every service
// already calls AuditService::log() for the audit trail. Queued via
// GenericAlertNotification's ShouldQueue, so this never blocks the request.
class NotificationService
{
    public function __construct(
        private CommunicationService $communications,
    ) {}

    // Transactional email to a customer about their own account activity (loan
    // disbursed, request approved/rejected, etc.) — distinct from notifyPermission()/
    // notifyUser() above, which alert internal admins. Routed through
    // CommunicationService so every send is logged in the communications table (status,
    // failure reason) the same way every other customer email in this app is tracked.
    // Silently no-ops if the customer has no email on file — never blocks the caller's
    // own transaction on a missing contact detail.
    public function notifyCustomerEmail(Model $actor, ?Customer $customer, string $type, string $subject, string $body): void
    {
        if (! $customer?->email) {
            return;
        }

        $this->communications->sendEmail(
            actor: $actor,
            type: $type,
            recipient: $customer->email,
            body: $body,
            subject: $subject,
        );
    }
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
