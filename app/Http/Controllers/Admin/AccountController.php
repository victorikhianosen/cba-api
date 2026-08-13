<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\PlaceLienRequest;
use App\Jobs\SendAccountStatementEmailJob;
use App\Http\Requests\Admin\Account\UpdateAccountStatusRequest;
use App\Http\Resources\Admin\Account\AccountLienResource;
use App\Http\Resources\Admin\Account\AccountResource;
use App\Http\Resources\Admin\Posting\TransactionResource;
use App\Services\Account\AccountLienService;
use App\Services\Account\AccountService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountService $accounts,
        private AccountLienService $liens,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->accounts->list(
            $request->integer('per_page', 50),
            $request->string('search')->trim()->value() ?: null,
            $request->integer('customer_id') ?: null,
        );

        return $this->success(
            message: 'Accounts retrieved successfully.',
            data: AccountResource::collection($accounts),
        );
    }

    // Resolves an account by its exact account number — used by the "book an investment
    // straight from the account number" flow, which needs the account name/balance/currency
    // and owning customer without a separate customer-search step.
    public function lookup(Request $request): JsonResponse
    {
        $accountNumber = trim((string) $request->query('account_number'));

        if ($accountNumber === '') {
            return $this->error(
                message: 'The account_number query parameter is required.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $account = $this->accounts->findByAccountNumber($accountNumber);

        if (! $account) {
            return $this->error(
                message: 'No account found with that account number.',
                responseCode: '404',
                statusCode: 404,
            );
        }

        return $this->success(
            message: 'Account retrieved successfully.',
            data: new AccountResource($account),
        );
    }

    public function transactions(Request $request, int $id): JsonResponse
    {
        try {
            $this->accounts->find($id);

            $transactions = $this->accounts->transactionsFor($id, $request->integer('per_page', 20));

            return $this->success(
                message: 'Transactions retrieved successfully.',
                data: TransactionResource::collection($transactions),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function statement(Request $request, int $id)
    {
        try {
            $account = $this->accounts->find($id);

            $pdf = $this->accounts->renderStatementPdf(
                $account,
                $request->string('from')->trim()->value() ?: null,
                $request->string('to')->trim()->value() ?: null,
            );

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="statement_of_account.pdf"',
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function emailStatement(Request $request, int $id): JsonResponse
    {
        try {
            $account = $this->accounts->find($id);

            if (! $account->customer?->email) {
                return $this->error(
                    message: 'This customer does not have an email address on file.',
                    responseCode: '101',
                    statusCode: 422,
                );
            }

            SendAccountStatementEmailJob::dispatch(
                $account->id,
                $request->string('from')->trim()->value() ?: null,
                $request->string('to')->trim()->value() ?: null,
            );

            return $this->success(
                message: 'Statement of account is being sent to the customer.',
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $account = $this->accounts->find($id);

            return $this->success(
                message: 'Account retrieved successfully.',
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
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

    public function updateStatus(UpdateAccountStatusRequest $request, int $id): JsonResponse
    {
        try {
            $account = $this->accounts->updateStatus($id, $request->validated()['status']);

            return $this->success(
                message: "Account '{$account->status}' successfully.",
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
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

    // Only ever creates a pending lien request — it does not touch the account's
    // lien_balance. That happens when the request is approved (see AccountLienController).
    public function placeLien(PlaceLienRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $lien = $this->liens->place(
                $id,
                (string) $data['amount'],
                $data['reason'] ?? null,
                $data['remarks'] ?? null,
                $data['expires_at'] ?? null,
            );

            return $this->success(
                message: 'Lien request submitted for approval.',
                data: new AccountLienResource($lien),
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
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

    public function liens(Request $request, int $id): JsonResponse
    {
        try {
            $this->accounts->find($id);

            $liens = $this->liens->forAccount($id, $request->integer('per_page', 15));

            return $this->success(
                message: 'Liens retrieved successfully.',
                data: AccountLienResource::collection($liens),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function togglePnd(int $id): JsonResponse
    {
        try {
            $account = $this->accounts->togglePnd($id);

            return $this->success(
                message: 'Account PND ' . ($account->pnd ? 'enabled' : 'disabled') . ' successfully.',
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
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

    public function togglePnc(int $id): JsonResponse
    {
        try {
            $account = $this->accounts->togglePnc($id);

            return $this->success(
                message: 'Account PNC ' . ($account->pnc ? 'enabled' : 'disabled') . ' successfully.',
                data: new AccountResource($account),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested account was not found.',
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
}
