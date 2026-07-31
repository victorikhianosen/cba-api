<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Posting\RejectPostingRequest;
use App\Http\Requests\Admin\Posting\StorePostingRequest;
use App\Http\Resources\Admin\Posting\FinancialTransactionResource;
use App\Http\Resources\Admin\Posting\PostingResource;
use App\Jobs\BulkApprovePostingsJob;
use App\Services\Posting\PostingService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PostingController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(
        private PostingService $postings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $postings = $this->postings->list(
            $request->integer('per_page', 15),
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
            $request->string('type')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Postings retrieved successfully.',
            data: PostingResource::collection($postings),
        );
    }

    public function show(int $id): JsonResponse
    {
        $posting = $this->postings->find($id);

        return $this->success(
            message: 'Posting retrieved successfully.',
            data: new PostingResource($posting),
        );
    }

    public function store(StorePostingRequest $request): JsonResponse
    {
        try {
            $posting = $this->postings->create($request->validated());

            return $this->success(
                message: 'Posting created successfully and is pending approval.',
                data: new PostingResource($posting),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested source or destination was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    // Approving is the only action that creates the financial transaction and its ledger legs, all inside one transaction in PostingService::approve().
    public function approve(int $id): JsonResponse
    {
        try {
            $posting              = $this->postings->find($id);
            $financialTransaction = $this->postings->approve($posting);

            return $this->success(
                message: 'Posting approved and posted successfully.',
                data: new FinancialTransactionResource($financialTransaction),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested posting, source, or destination was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    // Approves any mix of the four posting-type combinations (GL↔GL, account↔account,
    // account↔GL, GL↔account) as long as each posting is pending — the type itself
    // never matters to approval, only the status does. Dispatched to a queued job so
    // the request returns immediately instead of blocking on up to 50 sequential
    // approvals; the caller just gets told the batch is on its way.
    public function bulkApprove(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $actor = Auth::guard('user')->user();

        BulkApprovePostingsJob::dispatch($ids, (int) $actor?->id);

        return $this->success(
            message: count($ids) . ' posting(s) queued for approval — they will update shortly.',
        );
    }

    public function exportCsvFile(Request $request)
    {
        [$from, $to, $search, $status, $type] = $this->exportFilters($request);

        $rows = $this->postings
            ->cursorForExport($from, $to, $search, $status, $type)
            ->map(fn ($posting) => [
                'Date'                  => optional($posting->created_at)->toDateString(),
                'Reference'             => $posting->reference,
                'Transaction Reference' => $posting->transaction_reference,
                'Source Type'           => $posting->source_type,
                'Source'                => $this->partyLabel($posting->source_type, $posting->source_id),
                'Destination Type'      => $posting->destination_type,
                'Destination'           => $this->partyLabel($posting->destination_type, $posting->destination_id),
                'Amount'                => (string) $posting->amount,
                'Fee'                   => (string) $posting->fee,
                'Vat'                   => (string) $posting->vat,
                'Total Amount'          => (string) $posting->total_amount,
                'Status'                => $posting->status,
                'Remarks'               => $posting->remarks,
            ]);

        return $this->exportCsv('postings.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        [$from, $to, $search, $status, $type] = $this->exportFilters($request);

        $rows = $this->postings
            ->cursorForExport($from, $to, $search, $status, $type)
            ->map(fn ($posting) => [
                'Date'                  => optional($posting->created_at)->toDateString(),
                'Reference'             => $posting->reference,
                'Transaction Reference' => $posting->transaction_reference,
                'Source Type'           => $posting->source_type,
                'Source'                => $this->partyLabel($posting->source_type, $posting->source_id),
                'Destination Type'      => $posting->destination_type,
                'Destination'           => $this->partyLabel($posting->destination_type, $posting->destination_id),
                'Amount'                => (string) $posting->amount,
                'Fee'                   => (string) $posting->fee,
                'Vat'                   => (string) $posting->vat,
                'Total Amount'          => (string) $posting->total_amount,
                'Status'                => $posting->status,
                'Remarks'               => $posting->remarks,
            ]);

        return $this->exportExcel('postings.xlsx', $rows);
    }

    public function exportPdfFile(Request $request)
    {
        [$from, $to, $search, $status, $type] = $this->exportFilters($request);

        $total = $this->postings->countForFilter($from, $to, $search, $status, $type);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} postings — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow the date range or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->postings
            ->cursorForExport($from, $to, $search, $status, $type)
            ->map(fn ($posting) => [
                optional($posting->created_at)->toDateString(),
                $posting->reference,
                $this->partyLabel($posting->source_type, $posting->source_id),
                $this->partyLabel($posting->destination_type, $posting->destination_id),
                number_format((float) $posting->amount, 2),
                strtoupper($posting->status),
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'Postings',
            'headers' => ['Date', 'Reference', 'Source', 'Destination', 'Amount', 'Status'],
            'rows'    => $rows,
            'from'    => $from,
            'to'      => $to,
        ], 'postings.pdf');
    }

    private function exportFilters(Request $request): array
    {
        return [
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
            $request->string('type')->trim()->value() ?: null,
        ];
    }

    // Same party resolution as PostingResource — just flattened to a single display string for exports.
    private function partyLabel(?string $type, ?int $id): ?string
    {
        if (! $type || ! $id) {
            return null;
        }

        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type);
        $model = $class ? $class::find($id) : null;

        if (! $model) {
            return null;
        }

        return $model instanceof \App\Models\Account
            ? "{$model->account_name} ({$model->account_number})"
            : "{$model->name} ({$model->gl_code})";
    }

    public function reject(RejectPostingRequest $request, int $id): JsonResponse
    {
        try {
            $posting = $this->postings->find($id);
            $posting = $this->postings->reject($posting, $request->validated()['reason']);

            return $this->success(
                message: 'Posting rejected successfully.',
                data: new PostingResource($posting),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested posting was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }
}
