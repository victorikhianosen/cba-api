<?php

namespace App\Services\Communication\Channels;

use App\Mail\CoreBankMail;
use App\Models\Communication;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements CommunicationChannel
{
    public function send(Communication $communication): void
    {
        Mail::to($communication->recipient)->send(new CoreBankMail(
            subjectLine: $communication->subject ?? 'Notification',
            content: $communication->body,
            files: $communication->payload['files'] ?? [],
        ));
    }
}
