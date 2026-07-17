<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsChannel implements CommunicationChannel
{
    public function send(Communication $communication): void
    {
        $response = Http::baseUrl(config('services.termii.base_url'))
            ->post('/api/sms/send', [
                'api_key' => config('services.termii.api_key'),
                'to'      => $this->normalizeRecipient($communication->recipient),
                'from'    => config('services.termii.sender_id'),
                'sms'     => $communication->body,
                'type'    => 'plain',
                'channel' => 'generic',
            ]);

        if ($response->failed() || $response->json('code') === 'error') {
            throw new RuntimeException($response->json('message') ?? 'Termii SMS send failed.');
        }
    }

    /**
     * Termii expects international format without a leading '+' (e.g.
     * 2348012345678). Local numbers are normalized assuming a Nigerian
     * '0' prefix, matching this app's customer base.
     */
    private function normalizeRecipient(string $recipient): string
    {
        $digits = preg_replace('/\D/', '', $recipient);

        return str_starts_with($digits, '0')
            ? '234' . substr($digits, 1)
            : $digits;
    }
}
