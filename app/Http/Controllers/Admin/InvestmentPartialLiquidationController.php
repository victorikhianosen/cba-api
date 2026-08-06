<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investment\RejectInvestmentRequest;
use App\Http\Requests\Admin\Investment\RequestPartialLiquidationRequest;
use App\Http\Resources\Admin\Investment\InvestmentPartialLiquidationResource;
use App\Services\Investment\InvestmentPartialLiquidationService;
use App\Services\Investment\InvestmentScheduleService;
use App\Services\Investment\InvestmentService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvestmentPartialLiquidationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private InvestmentPartialLiquidationService $liquidations,
        private InvestmentService $investments,
        private InvestmentScheduleService $schedules,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $liquidations = $this->liquidations->list(
            $request->integer('per_page', 15),
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Partial liquidation requests retrieved successfully.',
            data: InvestmentPartialLiquidationResource::collection($liquidations),
        );
    }

    public function show(int $id): JsonResponse
    {
        try {
            $liquidation = $this->liquidations->find($id);

            return $this->success(
                message: 'Partial liquidation request retrieved successfully.',
                data: new InvestmentPartialLiquidationResource($liquidation),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested partial liquidation request was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function store(RequestPartialLiquidationRequest $request, int $id): JsonResponse
    {
        try {
            $investment  = $this->investments->find($id);
            $validated   = $request->validated();
            $liquidation = $this->liquidations->request($investment, (string) $validated['amount'], $validated['reason']);

            return $this->success(
                message: 'Partial liquidation requested; awaiting approval.',
                data: new InvestmentPartialLiquidationResource($liquidation),
                responseCode: '000',
                statusCode: 201,
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

    // Read-only, side-effect-free preview for the MAKER, before a liquidation request even
    // exists: shows the currently persisted schedule next to what it would look like if a
    // request for the given (not-yet-submitted) amount were approved right now. Same
    // calculator previewSchedule() uses for an already-created request — this is just its
    // pre-creation counterpart, driven by a query amount instead of a persisted row.
    public function previewForInvestment(Request $request, int $id): JsonResponse
    {
        try {
            $investment = $this->investments->find($id);
            $product    = $investment->investmentProduct;

            $current = $this->investments->currentScheduleFor($id);

            $amount = (string) $request->query('amount', '0');

            if (bccomp($amount, '0', 6) <= 0 || bccomp($amount, (string) $investment->current_principal, 6) >= 0) {
                return $this->success(
                    message: 'Schedule preview retrieved successfully.',
                    data: ['current' => $current, 'projected' => null],
                );
            }

            $projectedPrincipal = bcsub((string) $investment->current_principal, $amount, 6);
            $tenureDays = max(1, Carbon::today()->diffInDays(Carbon::parse($investment->maturity_date)));

            $projected = $this->schedules->preview(
                product: $product,
                principal: (float) $projectedPrincipal,
                postingPeriod: $investment->interest_posting_period,
                tenureDays: (int) $tenureDays,
                startDate: Carbon::today(),
            );

            return $this->success(
                message: 'Schedule preview retrieved successfully.',
                data: ['current' => $current, 'projected' => $projected],
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

    // Read-only, side-effect-free preview for the approver: shows the currently persisted
    // schedule next to what the schedule would look like immediately after approval (same
    // pure calculator regenerateRemaining() itself uses under the hood, just not persisted).
    public function previewSchedule(int $id): JsonResponse
    {
        try {
            $liquidation = $this->liquidations->find($id);
            $investment  = $this->investments->find($liquidation->investment_id);
            $product     = $investment->investmentProduct;

            $current = $this->investments->currentScheduleFor($liquidation->investment_id);

            $projectedPrincipal = bcsub((string) $investment->current_principal, (string) $liquidation->amount, 6);
            $tenureDays = max(1, Carbon::today()->diffInDays(Carbon::parse($investment->maturity_date)));

            $projected = $this->schedules->preview(
                product: $product,
                principal: (float) $projectedPrincipal,
                postingPeriod: $investment->interest_posting_period,
                tenureDays: (int) $tenureDays,
                startDate: Carbon::today(),
            );

            return $this->success(
                message: 'Schedule preview retrieved successfully.',
                data: [
                    'current'   => $current,
                    'projected' => $projected,
                ],
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested partial liquidation request or investment was not found.',
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

    public function approve(int $id): JsonResponse
    {
        try {
            $liquidation = $this->liquidations->find($id);
            $liquidation = $this->liquidations->approve($liquidation);

            return $this->success(
                message: 'Partial liquidation approved and posted successfully.',
                data: new InvestmentPartialLiquidationResource($liquidation),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested partial liquidation request was not found.',
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
            $liquidation = $this->liquidations->find($id);
            $liquidation = $this->liquidations->reject($liquidation, $request->validated()['reason']);

            return $this->success(
                message: 'Partial liquidation rejected successfully.',
                data: new InvestmentPartialLiquidationResource($liquidation),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested partial liquidation request was not found.',
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
