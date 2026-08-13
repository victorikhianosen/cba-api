<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customer\ApproveCustomerRequest;
use App\Http\Requests\Admin\Customer\BulkImportCustomersRequest;
use App\Http\Requests\Admin\Customer\BulkUpdateCustomerRequest;
use App\Http\Requests\Admin\Customer\CloseCustomerRequest;
use App\Http\Requests\Admin\Customer\RejectCustomerRequest;
use App\Http\Requests\Admin\Customer\StoreCustomerAccountRequest;
use App\Http\Requests\Admin\Customer\StoreCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerAccountsStatusRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerRequest;
use App\Http\Requests\Admin\Customer\UpdateCustomerStatusRequest;
use App\Http\Requests\Admin\Customer\UpdateDocumentRequest;
use App\Http\Requests\Admin\Customer\UploadDocumentsRequest;
use App\Http\Resources\Admin\Account\AccountResource;
use App\Http\Resources\Admin\Customer\CustomerBulkImportRowResource;
use App\Http\Resources\Admin\Customer\CustomerListResource;
use App\Http\Resources\Admin\Customer\CustomerLookupResource;
use App\Http\Resources\Admin\Customer\CustomerResource;
use App\Http\Resources\Admin\Customer\DocumentResource;
use App\Models\Customer;
use App\Services\Account\AccountService;
use App\Services\Customer\CustomerService;
use App\Services\Customer\DocumentService;
use App\Traits\ApiResponse;
use App\Traits\Exportable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use ApiResponse;
    use Exportable;

    public function __construct(
        private CustomerService $customers,
        private DocumentService $documents,
        private AccountService $accounts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customers->list(
            $request->integer('per_page', 20),
            $request->string('search')->trim()->value() ?: null,
            $request->string('status')->trim()->value() ?: null,
            $request->string('customer_type')->trim()->value() ?: null,
        );

        return $this->success(
            message: 'Customers retrieved successfully.',
            data: CustomerListResource::collection($customers),
            meta: [
                // Global counts, independent of the current page/filters.
                'statistics' => [
                    'total_customers' => Customer::query()->count(),
                    'total_active'    => Customer::query()->where('status', 'active')->count(),
                    'total_pending'   => Customer::query()->where('status', 'pending')->count(),
                    'total_inactive'  => Customer::query()->where('status', 'inactive')->count(),
                    'total_closed'    => Customer::query()->where('status', 'closed')->count(),
                ],
            ],
        );
    }

    public function exportCsvFile(Request $request)
    {
        $search       = $request->string('search')->trim()->value() ?: null;
        $status       = $request->string('status')->trim()->value() ?: null;
        $customerType = $request->string('customer_type')->trim()->value() ?: null;

        $rows = $this->customers
            ->cursorForExport($search, $status, $customerType)
            ->map(fn ($customer) => $this->exportRow($customer));

        return $this->exportCsv('customers.csv', $rows);
    }

    public function exportExcelFile(Request $request)
    {
        $search       = $request->string('search')->trim()->value() ?: null;
        $status       = $request->string('status')->trim()->value() ?: null;
        $customerType = $request->string('customer_type')->trim()->value() ?: null;

        $rows = $this->customers
            ->cursorForExport($search, $status, $customerType)
            ->map(fn ($customer) => $this->exportRow($customer));

        return $this->exportExcel('customers.xlsx', $rows);
    }

    // A single-row template — column headers plus one example row — so an admin knows
    // exactly what each column means and what format is expected (dates, phone digits, etc.)
    // before filling in real data.
    public function bulkImportTemplate()
    {
        $rows = LazyCollection::make($this->customers->bulkImportTemplateRows());

        return $this->exportExcel('customer_bulk_import_template.xlsx', $rows);
    }

    public function bulkImport(BulkImportCustomersRequest $request): JsonResponse
    {
        $batchId = $this->customers->startBulkImport($request->file('file'));

        return $this->success(
            message: 'Bulk upload queued — the rows will be processed shortly.',
            data: ['batch_id' => $batchId],
            responseCode: '000',
            statusCode: 202,
        );
    }

    public function bulkImports(Request $request): JsonResponse
    {
        $batches = $this->customers->listBulkImportBatches($request->integer('per_page', 15));

        return $this->success(
            message: 'Bulk import batches retrieved successfully.',
            data: $batches->items(),
            meta: [
                'current_page' => $batches->currentPage(),
                'last_page'    => $batches->lastPage(),
                'per_page'     => $batches->perPage(),
                'total'        => $batches->total(),
            ],
        );
    }

    public function bulkImportShow(string $batchId, Request $request): JsonResponse
    {
        try {
            $result = $this->customers->findBulkImportBatch(
                $batchId,
                $request->integer('per_page', 20),
                $request->string('status')->trim()->value() ?: null,
            );
        } catch (ModelNotFoundException $e) {
            return $this->error(
                message: 'The requested bulk import batch was not found.',
                responseCode: '404',
                statusCode: 404,
            );
        }

        return $this->success(
            message: 'Bulk import batch retrieved successfully.',
            data: CustomerBulkImportRowResource::collection($result['rows']),
            meta: [
                'summary'      => $result['summary'],
                'current_page' => $result['rows']->currentPage(),
                'last_page'    => $result['rows']->lastPage(),
                'per_page'     => $result['rows']->perPage(),
                'total'        => $result['rows']->total(),
            ],
        );
    }

    public function exportPdfFile(Request $request)
    {
        $search       = $request->string('search')->trim()->value() ?: null;
        $status       = $request->string('status')->trim()->value() ?: null;
        $customerType = $request->string('customer_type')->trim()->value() ?: null;

        $total = $this->customers->countForFilter($search, $status, $customerType);

        if ($total > self::PDF_EXPORT_ROW_LIMIT) {
            return $this->error(
                message: "This filter matches {$total} customers — PDF export is limited to " . self::PDF_EXPORT_ROW_LIMIT . '. Narrow your search or use CSV export instead.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $rows = $this->customers
            ->cursorForExport($search, $status, $customerType)
            ->map(fn ($customer) => [
                $customer->cif_number,
                trim("{$customer->first_name} {$customer->last_name}") ?: $customer->business_name,
                $customer->customer_type,
                $customer->phone,
                $customer->email,
                $customer->branch?->name,
                strtoupper($customer->status),
            ])
            ->all();

        return $this->exportPdf('exports.table', [
            'title'   => 'Customers',
            'headers' => ['CIF Number', 'Name', 'Type', 'Phone', 'Email', 'Branch', 'Status'],
            'rows'    => $rows,
        ], 'customers.pdf');
    }

    /** @return array<string, mixed> */
    private function exportRow($customer): array
    {
        return [
            'CIF Number'   => $customer->cif_number,
            'Name'         => trim("{$customer->first_name} {$customer->last_name}") ?: $customer->business_name,
            'Type'         => $customer->customer_type,
            'Phone'        => $customer->phone,
            'Email'        => $customer->email,
            'BVN'          => $customer->bvn,
            'Branch'       => $customer->branch?->name,
            'Account Officer' => $customer->accountOfficer
                ? trim("{$customer->accountOfficer->first_name} {$customer->accountOfficer->last_name}")
                : null,
            'Status'       => $customer->status,
            'Created At'   => optional($customer->created_at)->toDateTimeString(),
        ];
    }

    // Resolves a customer by CIF number to their id/name — used to confirm a
    // guardian (or any other customer reference) before submitting a form.
    public function lookup(Request $request): JsonResponse
    {
        $cifNumber = trim((string) $request->query('cif_number'));

        if ($cifNumber === '') {
            return $this->error(
                message: 'The cif_number query parameter is required.',
                responseCode: '101',
                statusCode: 422,
            );
        }

        $customer = $this->customers->findByCifNumber($cifNumber);

        if (! $customer) {
            return $this->error(
                message: 'No customer found with that CIF number.',
                responseCode: '404',
                statusCode: 404,
            );
        }

        return $this->success(
            message: 'Customer found.',
            data: new CustomerLookupResource($customer),
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

    // Generic status change to anything in the `statuses` lookup table (dormant, frozen,
    // suspended, kyc_verified, blacklisted, etc.), with an optional comment — approve/reject/close
    // stay as their own endpoints since each has its own required side effects and fields.
    public function updateStatus(UpdateCustomerStatusRequest $request, int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $data     = $request->validated();
            $customer = $this->customers->updateStatus($customer, $data['status'], $data['comment'] ?? null);

            return $this->success(
                message: 'Customer status updated successfully.',
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

    public function resetPassword(int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $this->customers->resetPassword($customer);

            return $this->success(
                message: 'Password reset successfully. The new password has been emailed to the customer.',
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

    public function resetPin(int $id): JsonResponse
    {
        try {
            $customer = $this->customers->find($id);
            $this->customers->resetPin($customer);

            return $this->success(
                message: 'PIN reset successfully. The new PIN has been emailed to the customer.',
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
