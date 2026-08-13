<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Mail\CoreBankMail;
use App\Services\Setting\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// The single place every scheduled job (loan cron, investment cron, and anything added
// later) reports back to once it finishes. One report, one combined list — every item
// the job touched, success or failure, in the order it ran, with a plain-English reason
// next to anything that failed. Renders via the same Pdf::loadView()->output() pattern
// AccountService::renderStatementPdf() already uses for statements, and emails it to
// whichever addresses are configured in the `notifications.cron_reports.recipients`
// setting. Deliberately tolerant: a failure here (bad SMTP config, no recipients set) is
// logged and swallowed, never thrown — a report that fails to send must never look like
// the cron job itself failed.
class CronReportService
{
    private const SETTING_KEY = 'notifications.cron_reports.recipients';

    public function __construct(
        private SettingService $settings,
    ) {}

    /**
     * @param array<int, array{label: string, status: 'success'|'failed', amount: ?string, reference: ?string, detail: string}> $items
     *   One row per thing the job attempted, in run order. `detail` is a short plain-English
     *   note — what happened for a success, why it failed for a failure.
     */
    public function send(
        string $jobName,
        string $jobKey,
        array $items,
        \DateTimeInterface $startedAt,
        \DateTimeInterface $finishedAt,
        bool $dryRun = false,
    ): void {
        try {
            $recipients = $this->settings->get(self::SETTING_KEY, []);
            $recipients = is_array($recipients) ? array_values(array_filter($recipients)) : [];

            if (empty($recipients)) {
                Log::warning('cron_report_no_recipients', ['job' => $jobKey]);
                return;
            }

            $startedAt  = Carbon::instance($startedAt);
            $finishedAt = Carbon::instance($finishedAt);

            $totalRan        = count($items);
            $totalSuccessful = count(array_filter($items, fn ($item) => $item['status'] === 'success'));
            $totalFailed     = count(array_filter($items, fn ($item) => $item['status'] === 'failed'));
            $status          = $totalFailed === 0 ? 'Success' : 'Completed With Errors';

            $reportTitle = "{$jobName} Report — {$startedAt->format('d M Y')}";

            $pdf = Pdf::loadView('reports.cron-job', [
                'reportTitle'     => $reportTitle,
                'jobName'         => $jobName,
                'status'          => $status,
                'dryRun'          => $dryRun,
                'startedAt'       => $startedAt->format('d M Y, H:i:s'),
                'finishedAt'      => $finishedAt->format('d M Y, H:i:s'),
                'durationMs'      => $startedAt->diffInMilliseconds($finishedAt),
                'totalRan'        => $totalRan,
                'totalSuccessful' => $totalSuccessful,
                'totalFailed'     => $totalFailed,
                'items'           => $items,
                'generatedAt'     => now()->format('d M Y, H:i:s'),
            ])->setPaper('a4', 'portrait');

            $tempPath = storage_path('app/temp/cron-report-' . uniqid('', true) . '.pdf');
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            file_put_contents($tempPath, $pdf->output());

            $fileName = Str::slug($reportTitle) . '.pdf';

            try {
                $subject = ($dryRun ? '[Dry Run] ' : '') . $reportTitle;

                Mail::to($recipients)->sendNow(new CoreBankMail(
                    subjectLine: $subject,
                    content: "<strong>{$reportTitle}</strong><br><br>"
                        . "Total Ran: <strong>{$totalRan}</strong><br>"
                        . "Total Successful: <strong>{$totalSuccessful}</strong><br>"
                        . "Total Failed: <strong>{$totalFailed}</strong><br><br>"
                        . "The full list — every item, success or failed, with the reason why anything failed — is attached as a PDF.",
                    files: [$tempPath],
                    fileNames: [$tempPath => $fileName],
                ));
            } finally {
                @unlink($tempPath);
            }
        } catch (\Throwable $e) {
            Log::error('cron_report_send_failed', [
                'job'   => $jobKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
