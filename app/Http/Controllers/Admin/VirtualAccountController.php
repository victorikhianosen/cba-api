<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\CreateVirtualAccountRequest;
use App\Http\Requests\Admin\Account\SimulateVirtualAccountCreditRequest;
use App\Http\Requests\Admin\Account\UpdateVirtualAccountBeneficiaryRequest;
use App\Http\Requests\Admin\Account\UpdateVirtualAccountStatusRequest;
use App\Http\Resources\Admin\Account\VirtualAccountResource;
use App\Http\Resources\Admin\Posting\TransactionResource;
use App\Services\Account\VirtualAccountService;
use App\Services\Customer\CustomerService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VirtualAccountController extends Controller
{
    use ApiResponse;

    public function __construct(
        private VirtualAccountService $virtualAccounts,
        private CustomerService $customers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $virtualAccounts = $this->virtualAccounts->list(
            $request->integer('per_page', 50),
            $request->integer('customer_id') ?: null,
            $request->integer('parent_account_id') ?: null,
            $request->string('search')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Virtual accounts retrieved successfully.',
            data: VirtualAccountResource::collection($virtualAccounts),
        );
    }

    // Every virtual account belonging to one customer — mirrors how accounts are already
    // listed per customer, so a customer's Virtual Accounts read the same way its regular
    // Accounts do.
    public function forCustomer(Request $request, int $customerId): JsonResponse
    {
        try {
            $this->customers->find($customerId);

            $virtualAccounts = $this->virtualAccounts->list(
                $request->integer('per_page', 50),
                $customerId,
            );

            return $this->success(
                message: 'Virtual accounts retrieved successfully.',
                data: VirtualAccountResource::collection($virtualAccounts),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $virtualAccount = $this->virtualAccounts->find($id);

            return $this->success(
                message: 'Virtual account retrieved successfully.',
                data: new VirtualAccountResource($virtualAccount),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested virtual account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function store(CreateVirtualAccountRequest $request, int $customerId): JsonResponse
    {
        try {
            $customer       = $this->customers->find($customerId);
            $virtualAccount = $this->virtualAccounts->create($customer, $request->validated());

            return $this->success(
                message: 'Virtual account opened successfully.',
                data: new VirtualAccountResource($virtualAccount),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer, parent account or virtual account product was not found.',
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

    public function updateBeneficiary(UpdateVirtualAccountBeneficiaryRequest $request, int $id): JsonResponse
    {
        try {
            $virtualAccount = $this->virtualAccounts->find($id);
            $virtualAccount = $this->virtualAccounts->updateBeneficiaryName($virtualAccount, $request->validated()['beneficiary_name']);

            return $this->success(
                message: 'Virtual account beneficiary name updated successfully.',
                data: new VirtualAccountResource($virtualAccount),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested virtual account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    public function updateStatus(UpdateVirtualAccountStatusRequest $request, int $id): JsonResponse
    {
        try {
            $virtualAccount = $this->virtualAccounts->find($id);
            $virtualAccount = $this->virtualAccounts->updateStatus($virtualAccount, $request->validated()['status']);

            return $this->success(
                message: "Virtual account '{$virtualAccount->status}' successfully.",
                data: new VirtualAccountResource($virtualAccount),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested virtual account was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }
    }

    // Internal test/demo scaffolding — stands in for a real NUBAN provider's inbound-payment
    // webhook, which this phase does not integrate with. Immediately sweeps to the parent.
    public function simulateInboundPayment(SimulateVirtualAccountCreditRequest $request, int $id): JsonResponse
    {
        try {
            $virtualAccount = $this->virtualAccounts->find($id);
            $data           = $request->validated();

            $financialTransaction = $this->virtualAccounts->creditFromInboundPayment(
                $virtualAccount,
                (string) $data['amount'],
                $data['narration'] ?? null,
                $data['provider_reference'] ?? null,
            );

            return $this->success(
                message: 'Inbound payment simulated and swept to the parent account successfully.',
                data: [
                    'financial_transaction_id' => $financialTransaction->id,
                    'reference'                => $financialTransaction->reference,
                    'amount'                   => $financialTransaction->amount,
                    'transactions'             => TransactionResource::collection($financialTransaction->transactions),
                ],
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested virtual account was not found.',
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
