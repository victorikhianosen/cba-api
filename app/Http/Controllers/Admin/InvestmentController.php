<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investment\PreviewInvestmentScheduleRequest;
use App\Http\Requests\Admin\Investment\RejectInvestmentRequest;
use App\Http\Requests\Admin\Investment\RequestTopUpRequest;
use App\Http\Requests\Admin\Investment\StoreInvestmentRequest;
use App\Http\Resources\Admin\Investment\InvestmentResource;
use App\Http\Resources\Admin\Investment\InvestmentScheduleResource;
use App\Http\Resources\Admin\Investment\InvestmentTransactionResource;
use App\Jobs\SendInvestmentLetterEmailJob;
use App\Models\Investment;
use App\Services\Investment\InvestmentService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvestmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private InvestmentService $investments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $investments = $this->investments->list(
            $request->integer('per_page', 50),
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Investments retrieved successfully.',
            data: InvestmentResource::collection($investments),
            meta: [
                // Global counts, independent of the current page/filters — so the summary
                // cards read "across everything", not just whatever page happens to be showing.
                'statistics' => [
                    'total_investments' => Investment::query()->count(),
                    'total_active'      => Investment::query()->where('status', 'active')->count(),
                    'total_pending'     => Investment::query()->where('status', 'pending')->count(),
                    'total_topup_pending' => Investment::query()->whereNotNull('pending_topup_amount')->count(),
                ],
            ],
        );
    }

    public function store(StoreInvestmentRequest $request): JsonResponse
    {
        try {
            $investment = $this->investments->open($request->validated());

            return $this->success(
                message: 'Investment opened successfully.',
                data: new InvestmentResource($investment),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment product or source account was not found.',
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

    public function show(int $id): JsonResponse
    {
        try {
            $investment = $this->investments->find($id);

            return $this->success(
                message: 'Investment retrieved successfully.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
                responseCode: '404',
                statusCode: 404,
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

    public function transactions(Request $request, int $id): JsonResponse
    {
        try {
            $transactions = $this->investments->transactionsFor($id, $request->integer('per_page', 20));

            return $this->success(
                message: 'Investment transactions retrieved successfully.',
                data: InvestmentTransactionResource::collection($transactions),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function schedule(int $id): JsonResponse
    {
        try {
            $schedule = $this->investments->currentScheduleFor($id);

            return $this->success(
                message: 'Investment schedule retrieved successfully.',
                data: InvestmentScheduleResource::collection($schedule),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    // Deliberately separate from schedule() — only fetched when the UI's "Previous
    // Schedules" section is expanded, so a heavily topped-up investment doesn't download
    // every historical schedule version on every page load.
    public function previousSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $schedule = $this->investments->previousScheduleFor($id, $request->integer('per_page', 100));

            return $this->success(
                message: 'Previous investment schedules retrieved successfully.',
                data: InvestmentScheduleResource::collection($schedule),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function previewSchedule(PreviewInvestmentScheduleRequest $request): JsonResponse
    {
        try {
            $preview = $this->investments->previewSchedule($request->validated());

            return $this->success(
                message: 'Schedule preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment product was not found.',
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

    public function previewApproval(int $id): JsonResponse
    {
        try {
            $preview = $this->investments->previewApproval($this->investments->find($id));

            return $this->success(
                message: 'Approval preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function approve(int $id): JsonResponse
    {
        try {
            $investment = $this->investments->approve($this->investments->find($id));

            return $this->success(
                message: 'Investment approved and funded successfully.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function reject(RejectInvestmentRequest $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investments->reject($this->investments->find($id), $request->validated()['reason']);

            return $this->success(
                message: 'Investment rejected successfully.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function previewTopUp(RequestTopUpRequest $request, int $id): JsonResponse
    {
        try {
            $preview = $this->investments->previewTopUp($this->investments->find($id), (string) $request->validated()['amount']);

            $preview['current_schedule']   = InvestmentScheduleResource::collection($preview['current_schedule']);

            return $this->success(
                message: 'Top-up preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    // For the approve flow — the amount is already fixed (pending_topup_amount), so a
    // checker can see exactly what they're about to apply before confirming.
    public function previewPendingTopUp(int $id): JsonResponse
    {
        try {
            $preview = $this->investments->previewPendingTopUp($this->investments->find($id));

            $preview['current_schedule'] = InvestmentScheduleResource::collection($preview['current_schedule']);

            return $this->success(
                message: 'Pending top-up preview generated successfully.',
                data: $preview,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function requestTopUp(RequestTopUpRequest $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investments->requestTopUp($this->investments->find($id), (string) $request->validated()['amount']);

            return $this->success(
                message: 'Top-up requested; awaiting approval.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function approveTopUp(int $id): JsonResponse
    {
        try {
            $investment = $this->investments->approveTopUp($this->investments->find($id));

            return $this->success(
                message: 'Top-up approved and applied successfully.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function rejectTopUp(RejectInvestmentRequest $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investments->rejectTopUp($this->investments->find($id), $request->validated()['reason']);

            return $this->success(
                message: 'Top-up rejected successfully.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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

    public function letterPdf(int $id)
    {
        try {
            $investment = $this->investments->find($id);
            $data       = $this->investments->letterData($investment);

            return Pdf::loadView('investments.letter', $data)
                ->setPaper('a4', 'portrait')
                ->download("investment-letter-{$investment->account_number}.pdf");
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function emailLetter(int $id): JsonResponse
    {
        try {
            $investment = $this->investments->find($id);

            if (! $investment->customer?->email) {
                return $this->error(
                    message: 'This customer does not have an email address on file.',
                    responseCode: '101',
                    statusCode: 422,
                );
            }

            // Dispatched, not sent inline — PDF rendering + delivery happen on the queue
            // worker so this request returns immediately instead of the admin waiting on
            // mail delivery.
            SendInvestmentLetterEmailJob::dispatch($investment->id);

            return $this->success(
                message: 'Investment letter is being sent to the customer.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to send the investment letter. Please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    public function terminate(RejectInvestmentRequest $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investments->terminate($this->investments->find($id), $request->validated()['reason']);

            return $this->success(
                message: 'Investment terminated successfully.',
                data: new InvestmentResource($investment),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested investment was not found.',
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
