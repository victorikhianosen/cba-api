<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Communication\CommunicationService;
use App\Services\Investment\InvestmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendInvestmentLetterEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $investmentId,
    ) {}

    public function handle(InvestmentService $investments, CommunicationService $communications): void
    {
        $investment = $investments->find($this->investmentId);
        $customer   = $investment->customer;

        if (! $customer?->email) {
            return;
        }

        $data = $investments->letterData($investment);
        $pdf  = Pdf::loadView('investments.letter', $data)->setPaper('a4', 'portrait');

        $tempPath = storage_path('app/temp/investment-letter-' . $investment->account_number . '-' . uniqid() . '.pdf');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        $pdf->save($tempPath);

        try {
            // sendEmailWithAttachments() sends synchronously (sendNow) — the attachment
            // path is deleted right after this call (still within this job's own
            // handle()), so the mail must go out before that happens, not be queued a
            // second time. Also logs the send in the Communication module.
            $communications->sendEmailWithAttachments(
                actor: $customer,
                type: 'investment_letter',
                recipient: $customer->email,
                body: "Dear {$data['customerName']},<br><br>Please find attached your investment certificate for account {$investment->account_number}.",
                subject: 'Your Investment Certificate — ' . $investment->account_number,
                filePaths: [$tempPath],
            );
        } finally {
            @unlink($tempPath);
        }
    }
}
