<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\RejectLienRequest;
use App\Http\Requests\Admin\Account\ReleaseLienRequest;
use App\Http\Resources\Admin\Account\AccountLienResource;
use App\Services\Account\AccountLienService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountLienController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(private AccountLienService $liens) {}

    public function index(Request $request): JsonResponse
    {
        $liens = $this->liens->list(
            [
                'search'     => $request->string('search')->trim()->value() ?: null,
                'status'     => $request->string('status')->trim()->value() ?: null,
                'account_id' => $request->integer('account_id') ?: null,
            ],
            $request->integer('per_page', 15),
        );

        return $this->success(
            message: 'Liens retrieved successfully.',
            data: AccountLienResource::collection($liens),
            meta: [
                // Real totals across the whole table — not just the current page/filter.
                'statistics' => $this->liens->stats(),
            ],
        );
    }

    public function exportCsvFile(Request $request)
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;

        $rows = $this->liens
            ->cursorForExport($search, $status)
            ->map(fn ($lien) => $this->exportRow($lien));

        return $this->exportCsv('liens.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;

        $rows = $this->liens
            ->cursorForExport($search, $status)
            ->map(fn ($lien) => $this->exportRow($lien));

        return $this->exportExcel('liens.xlsx', $rows);
    }

    public function exportPdfFile(Request $request)
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $status = $request->string('status')->trim()->value() ?: null;

        $total = $this->liens->countForFilter($search, $status);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} liens — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow your search or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->liens
            ->cursorForExport($search, $status)
            ->map(fn ($lien) => [
                $lien->reference,
                $lien->account_number,
                $lien->customer?->cif_number,
                number_format((float) $lien->amount, 2),
                $lien->reason,
                strtoupper($lien->status),
                $lien->createdBy?->username,
                optional($lien->created_at)?->format('Y-m-d H:i'),
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'Liens',
            'headers' => ['Reference', 'Account', 'CIF Number', 'Amount', 'Reason', 'Status', 'Requested By', 'Requested At'],
            'rows'    => $rows,
        ], 'liens.pdf');
    }

    /** @return array<string, mixed> */
    private function exportRow($lien): array
    {
        return [
            'Reference'     => $lien->reference,
            'Account'       => $lien->account_number,
            'CIF Number'    => $lien->customer?->cif_number,
            'Amount'        => $lien->amount,
            'Reason'        => $lien->reason,
            'Status'        => $lien->status,
            'Requested By'  => $lien->createdBy?->username,
            'Approved By'   => $lien->approvedBy?->username,
            'Rejected By'   => $lien->rejectedBy?->username,
            'Released By'   => $lien->releaseBy?->username,
            'Requested At'  => optional($lien->created_at)?->toDateTimeString(),
        ];
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->success(
                message: 'Lien retrieved successfully.',
                data: new AccountLienResource($this->liens->find($id)),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested lien was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $lien = $this->liens->approve($id);

            return $this->success(
                message: 'Lien approved successfully.',
                data: new AccountLienResource($lien),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested lien was not found.',
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

    public function reject(RejectLienRequest $request, int $id): JsonResponse
    {
        try {
            $lien = $this->liens->reject($id, $request->validated()['reason']);

            return $this->success(
                message: 'Lien rejected successfully.',
                data: new AccountLienResource($lien),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested lien was not found.',
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

    public function release(ReleaseLienRequest $request, int $id): JsonResponse
    {
        try {
            $lien = $this->liens->release($id, $request->validated()['reason'] ?? null);

            return $this->success(
                message: 'Lien released successfully.',
                data: new AccountLienResource($lien),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested lien was not found.',
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
