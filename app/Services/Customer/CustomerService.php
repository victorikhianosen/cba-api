<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer;
use App\Services\Account\AccountService;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    private const HASHABLE_FIELDS = ['password', 'panic_password', 'pin'];

    /**
     * Document types every customer — individual or business — must have
     * uploaded (via DocumentService::upload()) before they can be approved.
     */
    private const REQUIRED_DOCUMENT_TYPES = ['profile_picture', 'utility_bill'];

    public function __construct(
        private AuditService $audit,
        private AccountService $accounts,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::query()->with(['branch', 'accountOfficer'])->latest()->paginate($perPage)->withQueryString();
    }

    private const RELATIONS = ['branch', 'accountOfficer', 'documents', 'addresses', 'nextOfKin', 'business', 'directors', 'signatories'];

    public function find(int $id): Customer
    {
        return Customer::with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        $actor = auth()->user();

        $profilePicture = $data['profile_picture'] ?? null;
        $utilityBill    = $data['utility_bill'] ?? null;
        $addresses      = $data['addresses'] ?? [];
        $nextOfKin      = $data['next_of_kin'] ?? [];
        $business       = $data['business'] ?? null;
        $directors      = $data['directors'] ?? [];
        $signatories    = $data['signatories'] ?? [];

        unset(
            $data['profile_picture'], $data['utility_bill'],
            $data['addresses'], $data['next_of_kin'], $data['business'], $data['directors'], $data['signatories'],
        );

        unset(
            $data['cif_number'], $data['status'],
            $data['approved_by'], $data['approved_at'],
            $data['rejected_by'], $data['rejected_at'], $data['rejection_reason'],
            $data['closed_by'], $data['closed_at'], $data['closure_reason'],
            $data['id_verified'], $data['face_verified'], $data['utility_verified'],
            $data['enable_internet_bank'], $data['enable_sms'], $data['enable_email'],
            $data['enable_reset_password'], $data['enable_panic_password'],
            $data['referral_code'],
        );

        $data['cif_number']    = $this->generateUniqueCifNumber();
        $data['referral_code'] = $this->generateUniqueReferralCode();

        if ($business) {
            $data['business_name'] = $business['business_name'] ?? null;
        }
        $data['status']        = 'pending';

        foreach (self::HASHABLE_FIELDS as $field) {
            if (! empty($data[$field])) {
                $data[$field] = bcrypt($data[$field]);
            }
        }

        $customer = DB::transaction(function () use ($data, $actor, $profilePicture, $utilityBill, $addresses, $nextOfKin, $business, $directors, $signatories) {
            $customer = Customer::create($data);

            foreach (['profile_picture' => $profilePicture, 'utility_bill' => $utilityBill] as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $customer->documents()->create([
                        'title'       => Str::of($type)->replace('_', ' ')->title()->value(),
                        'type'        => $type,
                        'path'        => $file->store("customer-documents/{$customer->id}", 's3'),
                        'uploaded_by' => $actor?->id,
                        'status'      => 'pending',
                    ]);
                }
            }

            foreach ($addresses as $address) {
                $customer->addresses()->create($address);
            }

            foreach ($nextOfKin as $kin) {
                $customer->nextOfKin()->create($kin);
            }

            if ($business) {
                $customer->business()->create($business);
            }

            $createdDirectors = [];

            foreach ($directors as $index => $director) {
                if (($director['passport_photo'] ?? null) instanceof UploadedFile) {
                    $director['passport_photo'] = $director['passport_photo']->store("director-photos/{$customer->id}", 's3');
                }

                $createdDirectors[$index] = $customer->directors()->create($director);
            }

            foreach ($signatories as $signatory) {
                $directorIndex = $signatory['director_index'] ?? null;
                unset($signatory['director_index']);

                $signatory['director_id'] = $directorIndex !== null
                    ? ($createdDirectors[$directorIndex]->id ?? null)
                    : null;

                if (($signatory['passport_photo'] ?? null) instanceof UploadedFile) {
                    $signatory['passport_photo'] = $signatory['passport_photo']->store("signatory-photos/{$customer->id}", 's3');
                }

                if (($signatory['signature'] ?? null) instanceof UploadedFile) {
                    $signatory['signature'] = $signatory['signature']->store("signatory-signatures/{$customer->id}", 's3');
                }

                $customer->signatories()->create($signatory);
            }

            return $customer;
        });

        $customer = $customer->fresh(self::RELATIONS);

        $this->audit->log(
            action: 'created',
            module: 'customers',
            auditable: $customer,
            after: [
                'cif_number'    => $customer->cif_number,
                'phone'         => $customer->phone,
                'status'        => $customer->status,
                'customer_type' => $customer->customer_type,
            ],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was onboarded by '{$actor?->username}' and is pending approval.",
        );

        return $customer;
    }

    /**
     * Customer type is permanent (individual/business/minor never changes
     * once set) and accounts belong to their own model/table entirely —
     * neither is ever touched here, no matter what the caller sends.
     */
    private const IMMUTABLE_FIELDS = [
        'cif_number', 'status', 'referral_code',
        'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'closed_by', 'closed_at', 'closure_reason',
        'id_verified', 'face_verified', 'utility_verified',
        'created_at', 'created_by',
        'accounts', 'account_id', 'account_ids',
    ];

    public function update(Customer $customer, array $data): Customer
    {
        if (array_key_exists('customer_type', $data) && $data['customer_type'] !== $customer->customer_type) {
            throw ValidationException::withMessages([
                'customer_type' => ["Customer type cannot be changed once set. This customer will remain '{$customer->customer_type}' forever."],
            ]);
        }

        $before = $customer->only(['phone', 'email', 'username', 'branch_id', 'account_officer_id']);
        $actor  = auth()->user();

        foreach (self::HASHABLE_FIELDS as $field) {
            if (! empty($data[$field])) {
                $data[$field] = bcrypt($data[$field]);
            }
        }

        unset($data['customer_type']);

        foreach (self::IMMUTABLE_FIELDS as $field) {
            unset($data[$field]);
        }

        $customer->update($data);
        $customer->refresh()->load(['branch', 'accountOfficer']);

        $this->audit->log(
            action: 'updated',
            module: 'customers',
            auditable: $customer,
            before: $before,
            after: $customer->only(['phone', 'email', 'username', 'branch_id', 'account_officer_id']),
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was updated by '{$actor?->username}'.",
        );

        return $customer;
    }

    public function approve(Customer $customer): Customer
    {
        if ($customer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Only pending customers can be approved. This customer is currently '{$customer->status}'."],
            ]);
        }

        $uploadedTypes = $customer->documents()->pluck('type');
        $missingTypes  = collect(self::REQUIRED_DOCUMENT_TYPES)->diff($uploadedTypes);

        if ($missingTypes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'documents' => ['Missing required document(s): ' . $missingTypes->implode(', ') . '.'],
            ]);
        }

        $actor = auth()->user();

        $customer->update([
            'status'      => 'active',
            'approved_by' => $actor?->id,
            'approved_at' => now(),
        ]);

        $customer->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'customers',
            auditable: $customer,
            before: ['status' => 'pending'],
            after: ['status' => $customer->status],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was approved by '{$actor?->username}'.",
        );

        $this->accounts->openDefaultAccountForCustomer($customer);

        return $customer;
    }

    public function reject(Customer $customer, string $reason): Customer
    {
        if ($customer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Only pending customers can be rejected. This customer is currently '{$customer->status}'."],
            ]);
        }

        $actor = auth()->user();

        $customer->update([
            'status'           => 'rejected',
            'rejected_by'      => $actor?->id,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        $customer->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'customers',
            auditable: $customer,
            before: ['status' => 'pending'],
            after: ['status' => $customer->status, 'rejection_reason' => $reason],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was rejected by '{$actor?->username}': {$reason}",
        );

        return $customer;
    }

    /**
     * Banking rule: an onboarded customer is never deleted — closing is a
     * status change (with a reason and an audit trail), never a hard or
     * soft delete.
     */
    public function close(Customer $customer, string $reason): Customer
    {
        if ($customer->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => ['This customer is already closed.'],
            ]);
        }

        $before = $customer->status;
        $actor  = auth()->user();

        $customer->update([
            'status'          => 'closed',
            'closed_by'       => $actor?->id,
            'closed_at'       => now(),
            'closure_reason'  => $reason,
        ]);

        $customer->refresh();

        $this->audit->log(
            action: 'closed',
            module: 'customers',
            auditable: $customer,
            before: ['status' => $before],
            after: ['status' => $customer->status, 'closure_reason' => $reason],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was closed by '{$actor?->username}': {$reason}",
        );

        return $customer;
    }

    public function toggleInternetBanking(Customer $customer): Customer
    {
        return $this->toggleFlag($customer, 'enable_internet_bank', 'internet banking access');
    }

    public function toggleSms(Customer $customer): Customer
    {
        return $this->toggleFlag($customer, 'enable_sms', 'SMS notifications');
    }

    public function toggleEmail(Customer $customer): Customer
    {
        return $this->toggleFlag($customer, 'enable_email', 'email notifications');
    }

    public function updateStatus(Customer $customer, string $status): Customer
    {
        $before = $customer->status;
        $actor  = auth()->user();

        $customer->update(['status' => $status]);
        $customer->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'customers',
            auditable: $customer,
            before: ['status' => $before],
            after: ['status' => $customer->status],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) status changed from '{$before}' to '{$customer->status}' by '{$actor?->username}'.",
        );

        return $customer;
    }

    private function toggleFlag(Customer $customer, string $field, string $label): Customer
    {
        $before = (bool) $customer->{$field};
        $actor  = auth()->user();

        $customer->update([$field => ! $before]);
        $customer->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'customers',
            auditable: $customer,
            before: [$field => $before],
            after: [$field => $customer->{$field}],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) {$label} was " . ($customer->{$field} ? 'enabled' : 'disabled') . " by '{$actor?->username}'.",
        );

        return $customer;
    }

    private function displayName(Customer $customer): string
    {
        return $customer->customer_type === 'business'
            ? (string) $customer->business_name
            : trim("{$customer->first_name} {$customer->last_name}");
    }

    private function generateUniqueCifNumber(): string
    {
        $next = Customer::count() + 1;

        do {
            $cif = 'CIF' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (Customer::where('cif_number', $cif)->exists());

        return $cif;
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }
}
