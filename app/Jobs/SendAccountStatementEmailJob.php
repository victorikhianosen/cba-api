<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Account\AccountService;
use App\Services\Communication\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAccountStatementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $accountId,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    public function handle(AccountService $accounts, CommunicationService $communications): void
    {
        $account  = $accounts->find($this->accountId);
        $customer = $account->customer;

        if (! $customer?->email) {
            return;
        }

        $pdfContents = $accounts->renderStatementPdf($account, $this->from, $this->to);

        // The temp filename (and, by default, the attachment's display name) must never
        // contain the account number — that number is also the PDF's open password, so
        // leaking it into the filename would defeat the point of encrypting it.
        $tempPath = storage_path('app/temp/statement-' . uniqid('', true) . '.pdf');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        file_put_contents($tempPath, $pdfContents);

        $customerName = trim("{$customer->first_name} {$customer->last_name}") ?: ($customer->business_name ?? '');

        try {
            // sendEmailWithAttachments() sends synchronously (sendNow) — the attachment
            // path is deleted right after this call (still within this job's own
            // handle()), so the mail must go out before that happens, not be queued a
            // second time. Also logs the send in the Communication module.
            $communications->sendEmailWithAttachments(
                actor: $customer,
                type: 'account_statement',
                recipient: $customer->email,
                body: "Dear {$customerName},<br><br>Please find attached your statement of account."
                    . "<br><br>This document is password-protected — use your account number to open it.",
                subject: 'Your Statement of Account',
                filePaths: [$tempPath],
                fileNames: [$tempPath => 'statement_of_account.pdf'],
            );
        } finally {
            @unlink($tempPath);
        }
    }
}
