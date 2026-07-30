<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Posting\TransactionListResource;
use App\Http\Resources\Admin\Posting\TransactionResource;
use App\Services\Transaction\TransactionService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(
        private TransactionService $transactions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = $this->transactions->list(
            $request->integer('per_page', 50),
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->integer('account_id') ?: null,
            $request->string('search')->trim()->value() ?: null,
            $request->string('transaction_type')->trim()->value() ?: null,
            $request->string('direction')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Transactions retrieved successfully.',
            data: TransactionListResource::collection($transactions),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            $transaction = $this->transactions->find($id);

            return $this->success(
                message: 'Transaction retrieved successfully.',
                data: new TransactionResource($transaction),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested transaction was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    // Every transaction_type value currently in use — feeds the type filter dropdown so
    // it stays accurate as new products (airtime, betting, interbank, ...) start posting.
    public function types(): JsonResponse
    {
        return $this->success(
            message: 'Transaction types retrieved successfully.',
            data: $this->transactions->distinctTransactionTypes(),
        );
    }

    public function exportCsvFile(Request $request)
    {
        $from            = $request->string('from')->trim()->value() ?: null;
        $to              = $request->string('to')->trim()->value() ?: null;
        $accountId       = $request->integer('account_id') ?: null;
        $search          = $request->string('search')->trim()->value() ?: null;
        $transactionType = $request->string('transaction_type')->trim()->value() ?: null;
        $direction       = $request->string('direction')->trim()->value() ?: null;
        $status          = $request->string('status')->trim()->value() ?: null;

        $rows = $this->transactions
            ->cursorForExport($from, $to, $accountId, $search, $transactionType, $direction, $status)
            ->map(fn ($transaction) => [
                'Date'                  => optional($transaction->posting_date)->toDateString(),
                'Reference'             => $transaction->reference,
                'Transaction Reference' => $transaction->transaction_reference,
                'Account Number'        => $transaction->account?->account_number,
                'Customer'              => $transaction->customer
                    ? trim("{$transaction->customer->first_name} {$transaction->customer->last_name}") ?: $transaction->customer->business_name
                    : null,
                'Category'              => $transaction->transaction_type,
                'Type'                  => $transaction->type,
                'Channel'               => $transaction->channel,
                'Provider'              => $transaction->service_provider,
                'Provider Reference'    => $transaction->provider_reference,
                'Counterparty'          => $transaction->counterparty_name,
                'Counterparty Account'  => $transaction->counterparty_account_number,
                'Amount'                => (string) $transaction->amount,
                'Balance Before'        => (string) $transaction->balance_before,
                'Balance After'         => (string) $transaction->balance_after,
                'Currency'              => $transaction->currency?->code,
                'Status'                => $transaction->status,
                'Narration'             => $transaction->narration,
            ]);

        return $this->exportCsv('transactions.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        $from            = $request->string('from')->trim()->value() ?: null;
        $to              = $request->string('to')->trim()->value() ?: null;
        $accountId       = $request->integer('account_id') ?: null;
        $search          = $request->string('search')->trim()->value() ?: null;
        $transactionType = $request->string('transaction_type')->trim()->value() ?: null;
        $direction       = $request->string('direction')->trim()->value() ?: null;
        $status          = $request->string('status')->trim()->value() ?: null;

        $rows = $this->transactions
            ->cursorForExport($from, $to, $accountId, $search, $transactionType, $direction, $status)
            ->map(fn ($transaction) => [
                'Date'                  => optional($transaction->posting_date)->toDateString(),
                'Reference'             => $transaction->reference,
                'Transaction Reference' => $transaction->transaction_reference,
                'Account Number'        => $transaction->account?->account_number,
                'Customer'              => $transaction->customer
                    ? trim("{$transaction->customer->first_name} {$transaction->customer->last_name}") ?: $transaction->customer->business_name
                    : null,
                'Category'              => $transaction->transaction_type,
                'Type'                  => $transaction->type,
                'Channel'               => $transaction->channel,
                'Provider'              => $transaction->service_provider,
                'Provider Reference'    => $transaction->provider_reference,
                'Counterparty'          => $transaction->counterparty_name,
                'Counterparty Account'  => $transaction->counterparty_account_number,
                'Amount'                => (string) $transaction->amount,
                'Balance Before'        => (string) $transaction->balance_before,
                'Balance After'         => (string) $transaction->balance_after,
                'Currency'              => $transaction->currency?->code,
                'Status'                => $transaction->status,
                'Narration'             => $transaction->narration,
            ]);

        return $this->exportExcel('transactions.xlsx', $rows);
    }

    public function exportPdfFile(Request $request)
    {
        $from            = $request->string('from')->trim()->value() ?: null;
        $to              = $request->string('to')->trim()->value() ?: null;
        $accountId       = $request->integer('account_id') ?: null;
        $search          = $request->string('search')->trim()->value() ?: null;
        $transactionType = $request->string('transaction_type')->trim()->value() ?: null;
        $direction       = $request->string('direction')->trim()->value() ?: null;
        $status          = $request->string('status')->trim()->value() ?: null;

        $total = $this->transactions->countForFilter($from, $to, $accountId, $search, $transactionType, $direction, $status);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} transactions — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow the date range or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->transactions
            ->cursorForExport($from, $to, $accountId, $search, $transactionType, $direction, $status)
            ->map(fn ($transaction) => [
                optional($transaction->posting_date)->toDateString(),
                $transaction->reference,
                $transaction->account?->account_number,
                $transaction->customer
                    ? trim("{$transaction->customer->first_name} {$transaction->customer->last_name}") ?: $transaction->customer->business_name
                    : '—',
                $transaction->counterparty_name ?: '—',
                $transaction->transaction_type ? strtoupper($transaction->transaction_type) : '—',
                strtoupper($transaction->type),
                number_format((float) $transaction->amount, 2),
                $transaction->currency?->code,
                strtoupper($transaction->status),
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'Transactions',
            'headers' => ['Date', 'Reference', 'Account', 'Customer', 'Counterparty', 'Category', 'Type', 'Amount', 'Currency', 'Status'],
            'rows'    => $rows,
            'from'    => $from,
            'to'      => $to,
        ], 'transactions.pdf');
    }
}
