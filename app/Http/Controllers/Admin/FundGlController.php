<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FundGl\RejectFundGlRequest;
use App\Http\Requests\Admin\FundGl\StoreFundGlRequest;
use App\Http\Resources\Admin\FundGl\FundGlResource;
use App\Http\Resources\Admin\Posting\FinancialTransactionResource;
use App\Services\FundGl\FundGlService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FundGlController extends Controller
{
    use ApiResponse;

    public function __construct(
        private FundGlService $fundGls,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $fundGls = $this->fundGls->list(
            $request->integer('per_page', 15),
            $request->string('from')->trim()->value() ?: null,
            $request->string('to')->trim()->value() ?: null,
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Fund GL requests retrieved successfully.',
            data: FundGlResource::collection($fundGls),
        );
    }

    public function show(int $id): JsonResponse
    {
        $fundGl = $this->fundGls->find($id);

        return $this->success(
            message: 'Fund GL request retrieved successfully.',
            data: new FundGlResource($fundGl),
        );
    }

    public function store(StoreFundGlRequest $request): JsonResponse
    {
        try {
            $fundGl = $this->fundGls->create($request->validated());

            return $this->success(
                message: 'Fund GL request created successfully and is pending review.',
                data: new FundGlResource($fundGl),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested source or destination GL was not found.',
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

    public function review(int $id): JsonResponse
    {
        try {
            $fundGl = $this->fundGls->find($id);
            $fundGl = $this->fundGls->review($fundGl);

            return $this->success(
                message: 'Fund GL request reviewed successfully and is pending approval.',
                data: new FundGlResource($fundGl),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested fund GL request was not found.',
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

    // Approving is the only action that creates the financial transaction and its ledger
    // legs, all inside one transaction in FundGlService::approve().
    public function approve(int $id): JsonResponse
    {
        try {
            $fundGl                = $this->fundGls->find($id);
            $financialTransaction  = $this->fundGls->approve($fundGl);

            return $this->success(
                message: 'Fund GL request approved and posted successfully.',
                data: new FinancialTransactionResource($financialTransaction),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested fund GL request, source, or destination was not found.',
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

    public function reject(RejectFundGlRequest $request, int $id): JsonResponse
    {
        try {
            $fundGl = $this->fundGls->find($id);
            $fundGl = $this->fundGls->reject($fundGl, $request->validated()['reason']);

            return $this->success(
                message: 'Fund GL request rejected successfully.',
                data: new FundGlResource($fundGl),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested fund GL request was not found.',
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
