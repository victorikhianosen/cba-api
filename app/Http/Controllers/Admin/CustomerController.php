<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customer\ApproveCustomerRequest;
use App\Http\Requests\Admin\Customer\BulkUpdateCustomerRequest;
use App\Http\Requests\Admin\Customer\CloseCustomerRequest;
use App\Http\Requests\Admin\Customer\RejectCustomerRequest;
use App\Http\Requests\Admin\Customer\StoreCustomerAccountRequest;
use App\Http\Requests\Admin\Customer\StoreCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerAccountsStatusRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateDocumentRequest;
use App\Http\Requests\Admin\Customer\UploadDocumentsRequest;
use App\Http\Resources\Admin\Account\AccountResource;
use App\Http\Resources\Admin\Customer\CustomerListResource;
use App\Http\Resources\Admin\Customer\CustomerResource;
use App\Http\Resources\Admin\Customer\DocumentResource;
use App\Services\Account\AccountService;
use App\Services\Customer\CustomerService;
use App\Services\Customer\DocumentService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CustomerService $customers,
        private DocumentService $documents,
        private AccountService $accounts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customers->list($request->integer('per_page', 15));

        return $this->success(
            message: 'Customers retrieved successfully.',
            data: CustomerListResource::collection($customers),
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

    public function bulkUpdate(BulkUpdateCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customerIds = $data['customer_ids'];
        unset($data['customer_ids']);

        try {
            $result = $this->customers->bulkUpdate($customerIds, $data);

            return $this->success(
                message: 'Bulk update complete: ' . count($result['updated']) . ' updated, ' . count($result['skipped']) . ' skipped.',
                data: $result,
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

    public function approve(ApproveCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $this->customers->approve($customer, $request->validated());

            return $this->success(
                message: 'Customer approved successfully.',
                data: new CustomerResource($this->customers->find($id)),
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

    // Closing a customer is a status change, never a delete — the only way to "remove" a customer, matching real banking practice.
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

    // No hard or soft delete for customers — DELETE reuses the close() status-change flow.
    public function destroy(CloseCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->close($customer, $request->validated()['reason']);

            return $this->success(
                message: 'Customer deleted successfully.',
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

    // Accepts one or more files under any field name — each field name becomes that document's type/title.
    public function uploadDocuments(UploadDocumentsRequest $request, int $id): JsonResponse
    {
        try {
            $customer  = $this->customers->find($id);
            $documents = $this->documents->upload($customer, $request->allFiles());

            return $this->success(
                message: 'Document(s) uploaded successfully.',
                data: DocumentResource::collection($documents),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    // Lets an already onboarded customer open an additional account; the whole operation is one transaction, so a failure never leaves a half-created account.
    public function storeAccount(StoreCustomerAccountRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $account  = $this->accounts->openAccountForCustomer($customer, $request->validated());

            return $this->success(
                message: 'Account opened successfully.',
                data: new AccountResource($account),
                responseCode: '000',
                statusCode: 201,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer or account product was not found.',
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

    // Cascades a status change to every account this customer owns; the customer record itself is untouched.
    public function updateAccountsStatus(UpdateCustomerAccountsStatusRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $status   = $request->validated()['status'];

            $accounts = DB::transaction(function () use ($customer, $status) {
                $this->customers->updateStatus($customer, $status);

                return $this->accounts->updateStatusForCustomer($customer, $status);
            });

            return $this->success(
                message: "Account '{$status}' successfully.",
                data: AccountResource::collection($accounts),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    public function toggleInternetBanking(int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->toggleInternetBanking($customer);

            return $this->success(
                message: 'Internet banking access ' . ($customer->enable_internet_bank ? 'enabled' : 'disabled') . ' successfully.',
                // data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    public function toggleSms(int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->toggleSms($customer);

            return $this->success(
                message: 'SMS notifications ' . ($customer->enable_sms ? 'enabled' : 'disabled') . ' successfully.',
                // data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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

    public function toggleEmail(int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $customer = $this->customers->toggleEmail($customer);

            return $this->success(
                message: 'Email notifications ' . ($customer->enable_email ? 'enabled' : 'disabled') . ' successfully.',
                // data: new CustomerResource($customer),
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested customer was not found.',
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
