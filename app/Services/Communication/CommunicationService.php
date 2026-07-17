<?php

namespace App\Services\Communication;

use App\Jobs\SendCommunicationJob;
use App\Models\Communication;
use App\Models\Customer;
use App\Services\Audit\AuditService;
use App\Services\Communication\Channels\CommunicationChannel;
use App\Services\Communication\Channels\EmailChannel;
use App\Services\Communication\Channels\SmsChannel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class CommunicationService
{
    /**
     * Channel name => driver class. Adding a new channel (whatsapp, push,
     * etc.) later is just a new entry here plus a class implementing
     * CommunicationChannel — nothing else in this service changes.
     */
    private const DRIVERS = [
        'email' => EmailChannel::class,
        'sms'   => SmsChannel::class,
    ];

    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 20, ?string $search = null, ?string $channel = null, ?string $status = null): LengthAwarePaginator
    {
        return Communication::query()
            ->when($channel, fn ($query) => $query->where('channel', $channel))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Communication
    {
        return Communication::findOrFail($id);
    }

    /**
     * Send a single communication and log the outcome regardless of whether
     * the underlying channel succeeds — this table is the full record of
     * every email/SMS/etc. the app has ever attempted to send.
     */
    public function send(
        Model $actor,
        string $channel,
        string $recipient,
        string $body,
        ?string $subject = null,
        array $payload = [],
        string $type = 'manual',
    ): Communication {
        $communication = Communication::create([
            'actor_id'   => $actor->getKey(),
            'actor_type' => get_class($actor),
            'channel'    => $channel,
            'type'       => $type,
            'recipient'  => $recipient,
            'subject'    => $subject,
            'body'       => $body,
            'payload'    => $payload,
            'status'     => 'pending',
        ]);

        try {
            $this->driver($channel)->send($communication);

            $communication->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $communication->update([
                'status'         => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            Log::error('Communication send failed', [
                'communication_id' => $communication->id,
                'channel'          => $channel,
                'error'            => $e->getMessage(),
            ]);
        }

        return $communication->fresh();
    }

    public function sendEmail(
        Model $actor,
        string $type,
        string $recipient,
        string $body,
        ?string $subject = null,
        array $payload = [],
    ): Communication {
        return $this->send(
            actor: $actor,
            channel: 'email',
            recipient: $recipient,
            body: $body,
            subject: $subject,
            payload: $payload,
            type: $type,
        );
    }

    /**
     * Queue a send to an explicit list of customers, respecting each
     * customer's channel opt-in flag. Delivery happens off-request via
     * SendCommunicationJob, so this only validates and enqueues — it
     * returns the customer ids accepted onto the queue plus a list of
     * customers skipped up front (not found, no contact, or opted out).
     *
     * @param  int[]  $customerIds
     */
    public function sendBulkToCustomers(
        array $customerIds,
        string $channel,
        string $body,
        ?string $subject,
        string $type,
        Model $performer,
    ): array {
        $queued  = [];
        $skipped = [];

        $customers = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

        foreach ($customerIds as $customerId) {
            $customer = $customers->get($customerId);

            if (! $customer) {
                $skipped[] = ['customer_id' => $customerId, 'reason' => 'Customer not found.'];
                continue;
            }

            $recipient = $channel === 'email' ? $customer->email : $customer->phone;

            if (! $recipient) {
                $skipped[] = ['customer_id' => $customerId, 'reason' => "Customer has no {$channel} contact on file."];
                continue;
            }

            $optedIn = $channel === 'email' ? $customer->enable_email : $customer->enable_sms;

            if (! $optedIn) {
                $skipped[] = ['customer_id' => $customerId, 'reason' => "Customer has opted out of {$channel} communications."];
                continue;
            }

            SendCommunicationJob::dispatch(
                actorType: get_class($customer),
                actorId: $customer->id,
                channel: $channel,
                recipient: $recipient,
                body: $body,
                subject: $subject,
                type: $type,
            );

            $queued[] = $customerId;
        }

        $this->audit->log(
            action: 'bulk_queued',
            module: 'communications',
            description: "Bulk {$channel} queued for " . count($queued) . " customer(s), " . count($skipped) . " skipped, by '{$performer->username}'.",
            after: ['queued' => $queued, 'skipped' => $skipped],
            performer: $performer,
        );

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    private function driver(string $channel): CommunicationChannel
    {
        if (! isset(self::DRIVERS[$channel])) {
            throw new InvalidArgumentException("Unsupported communication channel '{$channel}'.");
        }

        return app(self::DRIVERS[$channel]);
    }
}
