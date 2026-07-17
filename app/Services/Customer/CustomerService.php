<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    private const HASHABLE_FIELDS = ['password', 'panic_password', 'pin'];

    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::query()->with(['branch', 'accountOfficer'])->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): Customer
    {
        return Customer::with(['branch', 'accountOfficer', 'documents'])->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        $actor = auth()->user();

        $documents = $data['documents'] ?? [];
        unset($data['documents']);

        unset(
            $data['cif_number'], $data['status'],
            $data['approved_by'], $data['approved_at'],
            $data['rejected_by'], $data['rejected_at'], $data['rejection_reason'],
            $data['closed_by'], $data['closed_at'], $data['closure_reason'],
            $data['id_verified'], $data['face_verified'], $data['utility_verified'],
        );

        $data['cif_number'] = $this->generateUniqueCifNumber();
        $data['status']     = 'pending';

        foreach (self::HASHABLE_FIELDS as $field) {
            if (! empty($data[$field])) {
                $data[$field] = bcrypt($data[$field]);
            }
        }

        $customer = Customer::create($data);

        foreach ($documents as $document) {
            $customer->documents()->create([
                'title'       => $document['title'] ?? null,
                'name'        => $document['name'] ?? null,
                'path'        => $document['path'] ?? null,
                'type'        => $document['type'] ?? null,
                'uploaded_by' => $actor?->id,
                'status'      => 'pending',
            ]);
        }

        $customer = $customer->fresh(['branch', 'accountOfficer', 'documents']);

        $this->audit->log(
            action: 'created',
            module: 'customers',
            auditable: $customer,
            after: ['cif_number' => $customer->cif_number, 'phone' => $customer->phone, 'status' => $customer->status, 'documents_count' => count($documents)],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was onboarded by '{$actor?->username}' with ".count($documents)." document(s) and is pending approval.",
        );

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $before = $customer->only(['phone', 'email', 'username', 'branch_id', 'account_officer_id']);
        $actor  = auth()->user();

        foreach (self::HASHABLE_FIELDS as $field) {
            if (! empty($data[$field])) {
                $data[$field] = bcrypt($data[$field]);
            }
        }

        unset(
            $data['cif_number'], $data['status'],
            $data['approved_by'], $data['approved_at'],
            $data['rejected_by'], $data['rejected_at'], $data['rejection_reason'],
            $data['closed_by'], $data['closed_at'], $data['closure_reason'],
            $data['id_verified'], $data['face_verified'], $data['utility_verified'],
        );

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
}
