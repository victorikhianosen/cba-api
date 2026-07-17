<?php

namespace App\Services\LoanProduct;

use App\Enums\LoanProductFinancialAccountType;
use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Models\LoanProduct;
use App\Models\ProductToGlAccountMapping;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanProductService
{
    private const CODE_PREFIX = 'LNS';

    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return LoanProduct::query()
            ->with('generalLedgerMappings.generalLedger')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): LoanProduct
    {
        return LoanProduct::with('generalLedgerMappings.generalLedger')->findOrFail($id);
    }

    public function create(array $data): LoanProduct
    {
        $actor = auth()->user();

        $loanProduct = DB::transaction(function () use ($data) {
            $currency = Currency::findOrFail($data['currency_id']);

            $this->assertGeneralLedgerMappingsAreValid($data['general_ledgers'], $currency);

            $generalLedgers = $data['general_ledgers'];
            unset($data['general_ledgers']);

            $data['code']            = $this->generateProductCode();
            $data['currency_code']   = $currency->code;
            $data['currency_digits'] = $data['currency_digits'] ?? 2;
            $data['created_by']      = auth()->id();

            $loanProduct = LoanProduct::create($data)->refresh();

            foreach ($generalLedgers as $mapping) {
                $type = LoanProductFinancialAccountType::fromName($mapping['financial_account_type']);

                $loanProduct->generalLedgerMappings()->create([
                    'general_ledger_id'           => $mapping['general_ledger_id'],
                    'financial_account_type'      => $type->value,
                    'financial_account_type_name' => $type->name,
                ]);
            }

            return $loanProduct->load('generalLedgerMappings.generalLedger');
        });

        $this->audit->log(
            action: 'created',
            module: 'loan_products',
            auditable: $loanProduct,
            after: $loanProduct->toArray(),
            description: "Loan product '{$loanProduct->name}' ({$loanProduct->code}) was created by '{$actor?->username}'.",
        );

        return $loanProduct;
    }

    public function update(LoanProduct $loanProduct, array $data): LoanProduct
    {
        $before = $loanProduct->toArray();
        $actor  = auth()->user();

        unset(
            $data['code'],
            $data['currency_id'],
            $data['currency_code'],
            $data['currency_digits'],
            $data['status'],
            $data['created_by'],
            $data['approved_by'],
            $data['approved_at'],
        );

        $loanProduct->update($data);
        $loanProduct->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'loan_products',
            auditable: $loanProduct,
            before: $before,
            after: $loanProduct->toArray(),
            description: "Loan product '{$loanProduct->name}' ({$loanProduct->code}) was updated by '{$actor?->username}'.",
        );

        return $loanProduct;
    }

    public function approve(LoanProduct $loanProduct): LoanProduct
    {
        if ($loanProduct->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Only pending loan products can be approved."],
            ]);
        }

        $before = $loanProduct->status;
        $actor  = auth()->user();

        $loanProduct->update([
            'status'      => 'active',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $loanProduct->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'loan_products',
            auditable: $loanProduct,
            before: ['status' => $before],
            after: ['status' => $loanProduct->status],
            description: "Loan product '{$loanProduct->name}' ({$loanProduct->code}) was approved by '{$actor?->username}'.",
        );

        return $loanProduct;
    }

    public function updateStatus(LoanProduct $loanProduct, string $status): LoanProduct
    {
        $before = $loanProduct->status;
        $actor  = auth()->user();

        $loanProduct->update(['status' => $status]);
        $loanProduct->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'loan_products',
            auditable: $loanProduct,
            before: ['status' => $before],
            after: ['status' => $loanProduct->status],
            description: "Loan product '{$loanProduct->name}' ({$loanProduct->code}) status changed from '{$before}' to '{$loanProduct->status}' by '{$actor?->username}'.",
        );

        return $loanProduct;
    }

    public function delete(LoanProduct $loanProduct): void
    {
        $name  = $loanProduct->name;
        $code  = $loanProduct->code;
        $actor = auth()->user();

        $loanProduct->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'loan_products',
            auditable: $loanProduct,
            before: ['name' => $name, 'code' => $code],
            description: "Loan product '{$name}' ({$code}) was deleted by '{$actor?->username}'.",
        );
    }

    private function assertGeneralLedgerMappingsAreValid(array $mappings, Currency $productCurrency): void
    {
        $providedTypes = collect($mappings)->pluck('financial_account_type');

        foreach ($providedTypes as $name) {
            if (! in_array($name, LoanProductFinancialAccountType::names(), true)) {
                throw ValidationException::withMessages([
                    'general_ledgers' => ["'{$name}' is not a valid financial account type for loan products."],
                ]);
            }
        }

        foreach (LoanProductFinancialAccountType::required() as $type) {
            if (! $providedTypes->contains($type->name)) {
                throw ValidationException::withMessages([
                    'general_ledgers' => ["{$type->label()} GL mapping is required."],
                ]);
            }
        }

        if ($providedTypes->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'general_ledgers' => ['Each financial account type can only be mapped once.'],
            ]);
        }

        $generalLedgerIds = collect($mappings)->pluck('general_ledger_id');

        if ($generalLedgerIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'general_ledgers' => ['A general ledger cannot be mapped more than once in the same request.'],
            ]);
        }

        foreach ($mappings as $mapping) {
            $ledger = GeneralLedger::findOrFail($mapping['general_ledger_id']);

            if ((int) $ledger->currency_id !== (int) $productCurrency->id) {
                throw ValidationException::withMessages([
                    'general_ledgers' => [
                        "Currency mismatch for ledger '{$ledger->name}'. Product currency is {$productCurrency->code} but ledger currency is " . ($ledger->currency_code ?? 'unset') . '.',
                    ],
                ]);
            }

            if (ProductToGlAccountMapping::where('general_ledger_id', $ledger->id)->exists()) {
                throw ValidationException::withMessages([
                    'general_ledgers' => ["General ledger '{$ledger->name}' is already mapped to another product."],
                ]);
            }
        }
    }

    private function generateProductCode(): string
    {
        $lastSequence = LoanProduct::where('code', 'like', self::CODE_PREFIX . '%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $lastSequence ? ((int) substr($lastSequence, strlen(self::CODE_PREFIX))) + 1 : 1;

        return self::CODE_PREFIX . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
