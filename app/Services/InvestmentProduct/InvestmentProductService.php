<?php

namespace App\Services\InvestmentProduct;

use App\Enums\SavingsProductFinancialAccountType;
use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Models\InvestmentProduct;
use App\Models\ProductToGlAccountMapping;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestmentProductService
{
    private const CODE_PREFIX = 'INV';

    public function __construct(
        private AuditService $audit,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return InvestmentProduct::query()
            ->with('generalLedgerMappings.generalLedger')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): InvestmentProduct
    {
        return InvestmentProduct::with('generalLedgerMappings.generalLedger')->findOrFail($id);
    }

    public function create(array $data): InvestmentProduct
    {
        $actor = auth()->user();

        $investmentProduct = DB::transaction(function () use ($data) {
            $currency = Currency::findOrFail($data['currency_id']);

            $this->assertGeneralLedgerMappingsAreValid($data['general_ledgers'], $currency);

            $generalLedgers = $data['general_ledgers'];
            unset($data['general_ledgers']);

            $data['code']            = $this->generateProductCode();
            $data['currency_code']   = $currency->code;
            $data['currency_digits'] = $data['currency_digits'] ?? 2;
            $data['created_by']      = auth()->id();

            $investmentProduct = InvestmentProduct::create($data)->refresh();

            foreach ($generalLedgers as $mapping) {
                $type = SavingsProductFinancialAccountType::fromName($mapping['financial_account_type']);

                $investmentProduct->generalLedgerMappings()->create([
                    'general_ledger_id'           => $mapping['general_ledger_id'],
                    'financial_account_type'      => $type->value,
                    'financial_account_type_name' => $type->name,
                ]);
            }

            return $investmentProduct->load('generalLedgerMappings.generalLedger');
        });

        $this->audit->log(
            action: 'created',
            module: 'investment_products',
            auditable: $investmentProduct,
            after: $investmentProduct->toArray(),
            description: "Investment product '{$investmentProduct->name}' ({$investmentProduct->code}) was created by '{$actor?->username}'.",
        );

        return $investmentProduct;
    }

    public function update(InvestmentProduct $investmentProduct, array $data): InvestmentProduct
    {
        $before = $investmentProduct->toArray();
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

        $investmentProduct->update($data);
        $investmentProduct->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'investment_products',
            auditable: $investmentProduct,
            before: $before,
            after: $investmentProduct->toArray(),
            description: "Investment product '{$investmentProduct->name}' ({$investmentProduct->code}) was updated by '{$actor?->username}'.",
        );

        return $investmentProduct;
    }

    public function approve(InvestmentProduct $investmentProduct): InvestmentProduct
    {
        if ($investmentProduct->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Only pending investment products can be approved."],
            ]);
        }

        $before = $investmentProduct->status;
        $actor  = auth()->user();

        $investmentProduct->update([
            'status'      => 'active',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $investmentProduct->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'investment_products',
            auditable: $investmentProduct,
            before: ['status' => $before],
            after: ['status' => $investmentProduct->status],
            description: "Investment product '{$investmentProduct->name}' ({$investmentProduct->code}) was approved by '{$actor?->username}'.",
        );

        return $investmentProduct;
    }

    public function updateStatus(InvestmentProduct $investmentProduct, string $status): InvestmentProduct
    {
        $before = $investmentProduct->status;
        $actor  = auth()->user();

        $investmentProduct->update(['status' => $status]);
        $investmentProduct->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'investment_products',
            auditable: $investmentProduct,
            before: ['status' => $before],
            after: ['status' => $investmentProduct->status],
            description: "Investment product '{$investmentProduct->name}' ({$investmentProduct->code}) status changed from '{$before}' to '{$investmentProduct->status}' by '{$actor?->username}'.",
        );

        return $investmentProduct;
    }

    public function delete(InvestmentProduct $investmentProduct): void
    {
        $name  = $investmentProduct->name;
        $code  = $investmentProduct->code;
        $actor = auth()->user();

        $investmentProduct->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'investment_products',
            auditable: $investmentProduct,
            before: ['name' => $name, 'code' => $code],
            description: "Investment product '{$name}' ({$code}) was deleted by '{$actor?->username}'.",
        );
    }

    private function assertGeneralLedgerMappingsAreValid(array $mappings, Currency $productCurrency): void
    {
        $providedTypes = collect($mappings)->pluck('financial_account_type');

        foreach ($providedTypes as $name) {
            if (! in_array($name, SavingsProductFinancialAccountType::names(), true)) {
                throw ValidationException::withMessages([
                    'general_ledgers' => ["'{$name}' is not a valid financial account type for investment products."],
                ]);
            }
        }

        foreach (SavingsProductFinancialAccountType::required() as $type) {
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
        $lastSequence = InvestmentProduct::where('code', 'like', self::CODE_PREFIX . '%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $lastSequence ? ((int) substr($lastSequence, strlen(self::CODE_PREFIX))) + 1 : 1;

        return self::CODE_PREFIX . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
