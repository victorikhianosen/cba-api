<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralLedger\StoreGeneralLedgerRequest;
use App\Http\Requests\Admin\GeneralLedger\UpdateGeneralLedgerRequest;
use App\Http\Requests\Admin\GeneralLedger\UpdateGeneralLedgerStatusRequest;
use App\Http\Resources\Admin\GeneralLedger\GeneralLedgerResource;
use App\Services\GeneralLedger\GeneralLedgerService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GeneralLedgerController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(
        private GeneralLedgerService $generalLedgers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $generalLedgers = $this->generalLedgers->list($request->integer('per_page', 50), $request->string('search')->trim()->value() ?: null);

        return $this->success(
            message: 'General ledgers retrieved successfully.',
            data: GeneralLedgerResource::collection($generalLedgers),
        );
    }

    public function stats(): JsonResponse
    {
        return $this->success(
            message: 'General ledger stats retrieved successfully.',
            data: $this->generalLedgers->stats(),
        );
    }

    public function exportCsvFile(Request $request)
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $rows = $this->generalLedgers
            ->cursorForExport($search)
            ->map(fn ($ledger) => [
                'GL Code'        => $ledger->gl_code,
                'Name'           => $ledger->name,
                'Classification' => $ledger->classification,
                'Type'           => $ledger->type,
                'Balance'        => (string) $ledger->balance,
                'Currency'       => $ledger->currency?->code,
                'Status'         => $ledger->status,
                'Assigned'       => $ledger->is_assigned ? 'Yes' : 'No',
            ]);

        return $this->exportCsv('general-ledgers.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $rows = $this->generalLedgers
            ->cursorForExport($search)
            ->map(fn ($ledger) => [
                'GL Code'        => $ledger->gl_code,
                'Name'           => $ledger->name,
                'Classification' => $ledger->classification,
                'Type'           => $ledger->type,
                'Balance'        => (string) $ledger->balance,
                'Currency'       => $ledger->currency?->code,
                'Status'         => $ledger->status,
                'Assigned'       => $ledger->is_assigned ? 'Yes' : 'No',
            ]);

        return $this->exportExcel('general-ledgers.xlsx', $rows);
    }

    public function exportPdfFile(Request $request)
    {
        $search = $request->string('search')->trim()->value() ?: null;

        $total = $this->generalLedgers->countForFilter($search);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} general ledgers — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow the search or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->generalLedgers
            ->cursorForExport($search)
            ->map(fn ($ledger) => [
                $ledger->gl_code,
                $ledger->name,
                ucfirst((string) $ledger->classification),
                number_format((float) $ledger->balance, 2),
                $ledger->currency?->code,
                strtoupper($ledger->status),
                $ledger->is_assigned ? 'Yes' : 'No',
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'General Ledgers',
            'headers' => ['GL Code', 'Name', 'Classification', 'Balance', 'Currency', 'Status', 'Assigned'],
            'rows'    => $rows,
            'from'    => null,
            'to'      => null,
        ], 'general-ledgers.pdf');
    }

    public function available(Request $request): JsonResponse
    {
        $generalLedgers = $this->generalLedgers->available($request->integer('per_page', 50));

        return $this->success(
            message: 'Available general ledgers retrieved successfully.',
            data: GeneralLedgerResource::collection($generalLedgers),
        );
    }

    public function show(int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        return $this->success(
            message: 'General ledger retrieved successfully.',
            data: new GeneralLedgerResource($generalLedger),
        );
    }

    public function store(StoreGeneralLedgerRequest $request): JsonResponse
    {
        try {
            $generalLedger = $this->generalLedgers->create($request->validated());

            return $this->success(
                message: 'General ledger created successfully.',
                data: new GeneralLedgerResource($generalLedger),
                responseCode: '000',
                statusCode: 201,
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

    public function update(UpdateGeneralLedgerRequest $request, int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        try {
            $generalLedger = $this->generalLedgers->update($generalLedger, $request->validated());

            return $this->success(
                message: 'General ledger updated successfully.',
                data: new GeneralLedgerResource($generalLedger),
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

    public function updateStatus(UpdateGeneralLedgerStatusRequest $request, int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        try {
            $generalLedger = $this->generalLedgers->updateStatus($generalLedger, $request->validated()['status']);

            return $this->success(
                message: 'General ledger status updated successfully.',
                data: new GeneralLedgerResource($generalLedger),
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

    public function destroy(int $id): JsonResponse
    {
        $generalLedger = $this->generalLedgers->find($id);

        try {
            $this->generalLedgers->delete($generalLedger);

            return $this->success(
                message: 'General ledger deleted successfully.',
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
