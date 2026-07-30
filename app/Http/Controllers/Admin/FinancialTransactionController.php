<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Posting\FinancialTransactionResource;
use App\Services\FinancialTransaction\FinancialTransactionService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(
        private FinancialTransactionService $financialTransactions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $this->financialTransactions->list(
            $request->integer('per_page', 50),
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
            $request->string('search')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Financial transactions retrieved successfully.',
            data: FinancialTransactionResource::collection($transactions),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            $transaction = $this->financialTransactions->find($id);

            return $this->success(
                message: 'Financial transaction retrieved successfully.',
                data: new FinancialTransactionResource($transaction),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested financial transaction was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function exportCsvFile(Request $request)
    {
        $from   = $request->string('from')->trim()->value() ?: null;
        $to     = $request->string('to')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;
        $search = $request->string('search')->trim()->value() ?: null;

        $rows = $this->financialTransactions
            ->cursorForExport($from, $to, $status, $search)
            ->map(fn ($transaction) => [
                'Date'                  => optional($transaction->created_at)->toDateTimeString(),
                'Transaction Reference' => $transaction->transaction_reference,
                'Customer'              => $transaction->customer
                    ? trim("{$transaction->customer->first_name} {$transaction->customer->last_name}") ?: $transaction->customer->business_name
                    : null,
                'Module'                => $transaction->module,
                'Type'                  => $transaction->transaction_type,
                'Amount'                => (string) $transaction->amount,
                'Fee'                   => (string) $transaction->fee,
                'VAT'                   => (string) $transaction->vat,
                'Currency'              => $transaction->currency?->code,
                'Status'                => $transaction->status,
            ]);

        return $this->exportCsv('financial-transactions.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        $from   = $request->string('from')->trim()->value() ?: null;
        $to     = $request->string('to')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;
        $search = $request->string('search')->trim()->value() ?: null;

        $rows = $this->financialTransactions
            ->cursorForExport($from, $to, $status, $search)
            ->map(fn ($transaction) => [
                'Date'                  => optional($transaction->created_at)->toDateTimeString(),
                'Transaction Reference' => $transaction->transaction_reference,
                'Customer'              => $transaction->customer
                    ? trim("{$transaction->customer->first_name} {$transaction->customer->last_name}") ?: $transaction->customer->business_name
                    : null,
                'Module'                => $transaction->module,
                'Type'                  => $transaction->transaction_type,
                'Amount'                => (string) $transaction->amount,
                'Fee'                   => (string) $transaction->fee,
                'VAT'                   => (string) $transaction->vat,
                'Currency'              => $transaction->currency?->code,
                'Status'                => $transaction->status,
            ]);

        return $this->exportExcel('financial-transactions.xlsx', $rows);
    }

    public function exportPdfFile(Request $request)
    {
        $from   = $request->string('from')->trim()->value() ?: null;
        $to     = $request->string('to')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;
        $search = $request->string('search')->trim()->value() ?: null;

        $total = $this->financialTransactions->countForFilter($from, $to, $status, $search);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} transactions — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow the date range or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->financialTransactions
            ->cursorForExport($from, $to, $status, $search)
            ->map(fn ($transaction) => [
                optional($transaction->created_at)->toDateTimeString(),
                $transaction->transaction_reference,
                $transaction->customer
                    ? trim("{$transaction->customer->first_name} {$transaction->customer->last_name}") ?: $transaction->customer->business_name
                    : '—',
                $transaction->transaction_type,
                number_format((float) $transaction->amount, 2),
                $transaction->currency?->code,
                strtoupper($transaction->status),
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'Financial Transactions',
            'headers' => ['Date', 'Reference', 'Customer', 'Type', 'Amount', 'Currency', 'Status'],
            'rows'    => $rows,
            'from'    => $from,
            'to'      => $to,
        ], 'financial-transactions.pdf');
    }
}
