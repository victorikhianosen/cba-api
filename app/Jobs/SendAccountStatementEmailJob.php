<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CoreBankMail;
use App\Services\Account\AccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

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

    public function handle(AccountService $accounts): void
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
            // sendNow(), not send() — the attachment path is deleted right after this
            // call (still within this job's own handle()), so the mail must go out
            // synchronously here rather than be queued a second time.
            Mail::to($customer->email)->sendNow(new CoreBankMail(
                subjectLine: 'Your Statement of Account',
                content: "Dear {$customerName},<br><br>Please find attached your statement of account."
                    . "<br><br>This document is password-protected — use your account number to open it.",
                files: [$tempPath],
                fileNames: [$tempPath => 'statement_of_account.pdf'],
            ));
        } finally {
            @unlink($tempPath);
        }
    }
}
