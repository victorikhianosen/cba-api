<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customer\CloseCustomerRequest;
use App\Http\Requests\Admin\Customer\RejectCustomerRequest;
use App\Http\Requests\Admin\Customer\StoreCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateDocumentRequest;
use App\Http\Resources\Admin\Customer\CustomerResource;
use App\Http\Resources\Admin\Customer\DocumentResource;
use App\Services\Customer\CustomerService;
use App\Services\Customer\DocumentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CustomerService $customers,
        private DocumentService $documents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customers->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Customers retrieved successfully.',
            data: CustomerResource::collection($customers),
        );
    }

    public function show(int $id): JsonResponse
    {
        $customer = $this->customers->find($id);

        return $this->success(
            message: 'Customer retrieved successfully.',
            data: new CustomerResource($customer),
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $customer = $this->customers->create($request->validated());

            return $this->success(
                message: 'Customer onboarded successfully and is pending approval.',
                data: new CustomerResource($customer),
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

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->update($customer, $request->validated());

            return $this->success(
                message: 'Customer updated successfully.',
                data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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
            $customer = $this->customers->find($id);
            $customer = $this->customers->approve($customer);

            return $this->success(
                message: 'Customer approved successfully.',
                data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    public function reject(RejectCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->reject($customer, $request->validated()['reason']);

            return $this->success(
                message: 'Customer rejected successfully.',
                data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    /**
     * Closing a customer is a status change, never a delete (soft or hard) —
     * this is the only way to "remove" a customer, matching real banking
     * practice.
     */
    public function close(CloseCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->close($customer, $request->validated()['reason']);

            return $this->success(
                message: 'Customer closed successfully.',
                data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    public function updateDocument(UpdateDocumentRequest $request, int $customerId, int $documentId): JsonResponse
    {
        try {
            $customer = $this->customers->find($customerId);
            $document = $this->documents->find($customer, $documentId);
            $document = $this->documents->update($document, $request->validated());

            return $this->success(
                message: 'Document updated successfully.',
                data: new DocumentResource($document),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer or document was not found.',
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
