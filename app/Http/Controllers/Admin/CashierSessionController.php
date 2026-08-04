<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashierSession\ApproveVarianceRequest;
use App\Http\Requests\Admin\CashierSession\CashMovementRequest;
use App\Http\Requests\Admin\CashierSession\CashTransferRequest;
use App\Http\Requests\Admin\CashierSession\CloseCashierSessionRequest;
use App\Http\Requests\Admin\CashierSession\OpenCashierSessionRequest;
use App\Http\Requests\Admin\CashierSession\RejectVarianceRequest;
use App\Http\Requests\Admin\CashierSession\RejectWithdrawalRequest;
use App\Http\Resources\Admin\CashierSession\CashierSessionResource;
use App\Http\Resources\Admin\CashierSession\CashierTransactionResource;
use App\Services\Teller\CashierSessionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CashierSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CashierSessionService $sessions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->sessions->list(
            $request->integer('per_page', 15),
            $request->integer('teller_id') ?: null,
            $request->integer('cashier_id') ?: null,
            $request->string('status')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Cashier sessions retrieved successfully.',
            data: CashierSessionResource::collection($sessions),
        );
    }

    public function show(int $id): JsonResponse
    {
        $session = $this->sessions->find($id);

        return $this->success(
            message: 'Cashier session retrieved successfully.',
            data: new CashierSessionResource($session),
        );
    }

    // The current cashier's own in-progress session, for the "my teller" screen.
    public function mine(): JsonResponse
    {
        $cashierId = Auth::guard('user')->id();
        $session   = $cashierId ? $this->sessions->mine($cashierId) : null;

        return $this->success(
            message: $session ? 'Current cashier session retrieved successfully.' : 'No cashier session currently open.',
            data: $session ? new CashierSessionResource($session->load(['teller', 'cashier', 'transactions'])) : null,
        );
    }

    public function open(OpenCashierSessionRequest $request): JsonResponse
    {
        $data      = $request->validated();
        $cashierId = Auth::guard('user')->id();

        try {
            $session = $this->sessions->openSession((int) $data['teller_id'], (int) $cashierId, (string) $data['opening_float_amount']);

            return $this->success(
                message: 'Cashier session opened successfully.',
                data: new CashierSessionResource($session),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function buy(CashMovementRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->sessions->buy($id, $data['counterparty_type'], (int) $data['counterparty_id'], (string) $data['amount'], $data['narration'] ?? null);

            return $this->success(message: 'Cash buy recorded successfully.', data: new CashierSessionResource($this->sessions->find($id)));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function sell(CashMovementRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->sessions->sell($id, $data['counterparty_type'], (int) $data['counterparty_id'], (string) $data['amount'], $data['narration'] ?? null);

            return $this->success(message: 'Cash sell recorded successfully.', data: new CashierSessionResource($this->sessions->find($id)));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    // Immediate — a customer handing cash to the teller, nothing to approve.
    public function deposit(CashTransferRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->sessions->deposit($id, $data['account_number'], (string) $data['amount'], $data['narration'] ?? null);

            return $this->success(message: 'Cash deposit recorded successfully.', data: new CashierSessionResource($this->sessions->find($id)));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    // Creates a PENDING withdrawal request — no cash moves until a second admin calls
    // approveWithdrawal().
    public function withdraw(CashTransferRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $transaction = $this->sessions->withdraw($id, $data['account_number'], (string) $data['amount'], $data['narration'] ?? null);

            return $this->success(
                message: 'Withdrawal requested — awaiting a second admin\'s approval.',
                data: new CashierTransactionResource($transaction),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function pendingWithdrawals(Request $request): JsonResponse
    {
        // status=all shows the full approve/reject history; otherwise defaults to pending-only.
        $status = $request->query('status', 'pending');
        $status = $status === 'all' ? null : $status;

        $transactions = $this->sessions->pendingWithdrawals($request->integer('per_page', 15), $status);

        return $this->success(
            message: 'Pending withdrawals retrieved successfully.',
            data: CashierTransactionResource::collection($transactions),
        );
    }

    public function approveWithdrawal(int $transactionId): JsonResponse
    {
        try {
            $transaction = $this->sessions->approveWithdrawal($transactionId);

            return $this->success(message: 'Withdrawal approved — cash may now be handed out.', data: new CashierTransactionResource($transaction));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function rejectWithdrawal(RejectWithdrawalRequest $request, int $transactionId): JsonResponse
    {
        try {
            $transaction = $this->sessions->rejectWithdrawal($transactionId, $request->validated()['reason']);

            return $this->success(message: 'Withdrawal rejected.', data: new CashierTransactionResource($transaction));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function close(CloseCashierSessionRequest $request, int $id): JsonResponse
    {
        try {
            $session = $this->sessions->closeSession($id, (string) $request->validated()['counted_closing_amount']);

            return $this->success(message: 'Cashier session closed — pending reconciliation.', data: new CashierSessionResource($session));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function reconcile(int $id): JsonResponse
    {
        try {
            $session = $this->sessions->reconcile($id);

            return $this->success(message: 'Cashier session reconciled.', data: new CashierSessionResource($session));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function approveVariance(ApproveVarianceRequest $request, int $id): JsonResponse
    {
        try {
            $session = $this->sessions->approveVariance($id, $request->validated()['remarks'] ?? null);

            return $this->success(message: 'Variance approved — cashier session reconciled and swept.', data: new CashierSessionResource($session));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }

    public function rejectVariance(RejectVarianceRequest $request, int $id): JsonResponse
    {
        try {
            $session = $this->sessions->rejectVariance($id, $request->validated()['reason']);

            return $this->success(message: 'Variance rejected — cashier must recount or escalate.', data: new CashierSessionResource($session));
        } catch (ValidationException $e) {
            return $this->error(message: $e->getMessage(), responseCode: '101', statusCode: 422, errors: $e->errors());
        } catch (\Throwable $e) {
            report($e);

            return $this->error(message: 'We are unable to process your request please try again.', responseCode: '500', statusCode: 500);
        }
    }
}
