<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Posting\JournalEntryResource;
use App\Services\JournalEntry\JournalEntryService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(
        private JournalEntryService $journalEntries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $entries = $this->journalEntries->list(
            $request->integer('per_page', 50),
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->integer('general_ledger_id') ?: null,
            $request->string('search')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Journal entries retrieved successfully.',
            data: JournalEntryResource::collection($entries),
        );
    }

    public function exportCsvFile(Request $request)
    {
        $from = $request->string('from')->trim()->value() ?: null;
        $to   = $request->string('to')->trim()->value() ?: null;
        $generalLedgerId = $request->integer('general_ledger_id') ?: null;
        $search = $request->string('search')->trim()->value() ?: null;

        $rows = $this->journalEntries
            ->cursorForExport($from, $to, $generalLedgerId, $search)
            ->map(fn ($entry) => [
                'Posting Date'           => optional($entry->posting_date)->toDateString(),
                'Journal Reference'      => $entry->journal_reference,
                'Transaction Reference'  => $entry->transaction_reference,
                'GL Code'                => $entry->generalLedger?->gl_code,
                'GL Name'                => $entry->generalLedger?->name,
                'Entry Type'             => $entry->entry_type,
                'Amount'                 => (string) $entry->amount,
                'Balance Before'         => (string) $entry->balance_before,
                'Balance After'          => (string) $entry->balance_after,
                'Currency'               => $entry->currency?->code,
                'Narration'              => $entry->narration,
            ]);

        return $this->exportCsv('journal-entries.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        $from = $request->string('from')->trim()->value() ?: null;
        $to   = $request->string('to')->trim()->value() ?: null;
        $generalLedgerId = $request->integer('general_ledger_id') ?: null;
        $search = $request->string('search')->trim()->value() ?: null;

        $rows = $this->journalEntries
            ->cursorForExport($from, $to, $generalLedgerId, $search)
            ->map(fn ($entry) => [
                'Posting Date'           => optional($entry->posting_date)->toDateString(),
                'Journal Reference'      => $entry->journal_reference,
                'Transaction Reference'  => $entry->transaction_reference,
                'GL Code'                => $entry->generalLedger?->gl_code,
                'GL Name'                => $entry->generalLedger?->name,
                'Entry Type'             => $entry->entry_type,
                'Amount'                 => (string) $entry->amount,
                'Balance Before'         => (string) $entry->balance_before,
                'Balance After'          => (string) $entry->balance_after,
                'Currency'               => $entry->currency?->code,
                'Narration'              => $entry->narration,
            ]);

        return $this->exportExcel('journal-entries.xlsx', $rows);
    }

    public function exportPdfFile(Request $request)
    {
        $from = $request->string('from')->trim()->value() ?: null;
        $to   = $request->string('to')->trim()->value() ?: null;
        $generalLedgerId = $request->integer('general_ledger_id') ?: null;
        $search = $request->string('search')->trim()->value() ?: null;

        $total = $this->journalEntries->countForFilter($from, $to, $generalLedgerId, $search);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} journal entries — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow the date range or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->journalEntries
            ->cursorForExport($from, $to, $generalLedgerId, $search)
            ->map(fn ($entry) => [
                optional($entry->posting_date)->toDateString(),
                $entry->journal_reference,
                $entry->generalLedger?->gl_code . ' — ' . $entry->generalLedger?->name,
                strtoupper($entry->entry_type),
                number_format((float) $entry->amount, 2),
                number_format((float) $entry->balance_after, 2),
                $entry->currency?->code,
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'Journal Entries',
            'headers' => ['Posting Date', 'Journal Ref', 'General Ledger', 'Type', 'Amount', 'Balance After', 'Currency'],
            'rows'    => $rows,
            'from'    => $from,
            'to'      => $to,
        ], 'journal-entries.pdf');
    }
}
