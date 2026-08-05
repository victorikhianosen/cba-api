<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoreBankMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $content,
        public array $files = [],
        // Optional path => display-name map. Without an entry, the attachment shows the
        // temp file's own basename — fine normally, but a problem when that basename
        // itself is sensitive (e.g. a statement PDF's temp filename embedding the
        // account number that also happens to be the PDF's open password).
        public array $fileNames = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

  
    public function content(): Content
{
    return new Content(
        view: 'emails.bank-template',
        with: [
            'content'     => $this->content,
            'companyName' => config('app.name'),
            'headerTitle' => $this->subjectLine,
        ]
    );
}

    public function attachments(): array
    {
        return collect($this->files)
            ->map(function ($file) {
                $attachment = Attachment::fromPath($file);

                if ($name = $this->fileNames[$file] ?? null) {
                    $attachment = $attachment->as($name);
                }

                return $attachment;
            })
            ->toArray();
    }
}