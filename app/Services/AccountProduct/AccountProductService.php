<?php

namespace App\Services\AccountProduct;

use App\Enums\AccountProductType;
use App\Models\AccountProduct;
use App\Models\Currency;
use App\Models\GeneralLedger;
use App\Models\ProductToGlAccountMapping;
use App\Services\Audit\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountProductService
{
    private const PRODUCT_TYPE_PREFIXES = [
        'savings'     => 'SAV',
        'current'     => 'CUR',
        'merchant'    => 'MCH',
        'corporate'   => 'COR',
        'domiciliary' => 'DOM',
    ];

    public function __construct(
        private AuditService $audit,
    ) {}

    /**
     * @param string|string[]|null $productType
     */
    public function list(int $perPage = 15, string|array|null $productType = null): LengthAwarePaginator
    {
        return AccountProduct::query()
            ->with('generalLedgerMappings.generalLedger')
            ->when($productType, fn ($query) => is_array($productType)
                ? $query->whereIn('product_type', $productType)
                : $query->where('product_type', $productType))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param string|string[]|null $productType
     */
    public function find(int $id, string|array|null $productType = null): AccountProduct
    {
        return AccountProduct::with('generalLedgerMappings.generalLedger')
            ->when($productType, fn ($query) => is_array($productType)
                ? $query->whereIn('product_type', $productType)
                : $query->where('product_type', $productType))
            ->findOrFail($id);
    }

    public function create(array $data): AccountProduct
    {
        $actor = auth()->user();

        $accountProduct = DB::transaction(function () use ($data) {
            $currency = Currency::findOrFail($data['currency_id']);

            $this->assertGeneralLedgerMappingsAreValid($data['general_ledgers'], $currency, $data['allow_overdraft'] ?? false);

            $generalLedgers = $data['general_ledgers'];
            unset($data['general_ledgers']);

            $data['code']            = $this->generateProductCode($data['product_type']);
            $data['currency_code']   = $currency->code;
            $data['created_by']      = auth()->id();

            $accountProduct = AccountProduct::create($data)->refresh();

            foreach ($generalLedgers as $mapping) {
                $type = AccountProductType::fromName($mapping['financial_account_type']);

                $accountProduct->generalLedgerMappings()->create([
                    'general_ledger_id'           => $mapping['general_ledger_id'],
                    'financial_account_type'      => $type->value,
                    'financial_account_type_name' => $type->name,
                ]);
            }

            return $accountProduct->load('generalLedgerMappings.generalLedger');
        });

        $this->audit->log(
            action: 'created',
            module: 'account_products',
            auditable: $accountProduct,
            after: $accountProduct->toArray(),
            description: "Account product '{$accountProduct->name}' ({$accountProduct->code}) was created by '{$actor?->username}'.",
        );

        return $accountProduct;
    }

    public function update(AccountProduct $accountProduct, array $data): AccountProduct
    {
        $before = $accountProduct->toArray();
        $actor  = auth()->user();

        unset(
            $data['code'],
            $data['product_type'],
            $data['currency_id'],
            $data['currency_code'],
            $data['status'],
            $data['created_by'],
            $data['approved_by'],
            $data['approved_at'],
        );

        $accountProduct->update($data);
        $accountProduct->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'account_products',
            auditable: $accountProduct,
            before: $before,
            after: $accountProduct->toArray(),
            description: "Account product '{$accountProduct->name}' ({$accountProduct->code}) was updated by '{$actor?->username}'.",
        );

        return $accountProduct;
    }

    public function approve(AccountProduct $accountProduct): AccountProduct
    {
        if ($accountProduct->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Only pending account products can be approved."],
            ]);
        }

        $before = $accountProduct->status;
        $actor  = auth()->user();

        $accountProduct->update([
            'status'      => 'active',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $accountProduct->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'account_products',
            auditable: $accountProduct,
            before: ['status' => $before],
            after: ['status' => $accountProduct->status],
            description: "Account product '{$accountProduct->name}' ({$accountProduct->code}) was approved by '{$actor?->username}'.",
        );

        return $accountProduct;
    }

    public function updateStatus(AccountProduct $accountProduct, string $status): AccountProduct
    {
        $before = $accountProduct->status;
        $actor  = auth()->user();

        $accountProduct->update(['status' => $status]);
        $accountProduct->refresh();

        $this->audit->log(
            action: 'status_updated',
            module: 'account_products',
            auditable: $accountProduct,
            before: ['status' => $before],
            after: ['status' => $accountProduct->status],
            description: "Account product '{$accountProduct->name}' ({$accountProduct->code}) status changed from '{$before}' to '{$accountProduct->status}' by '{$actor?->username}'.",
        );

        return $accountProduct;
    }

    public function delete(AccountProduct $accountProduct): void
    {
        $name  = $accountProduct->name;
        $code  = $accountProduct->code;
        $actor = auth()->user();

        $accountProduct->delete();

        $this->audit->log(
            action: 'deleted',
            module: 'account_products',
            auditable: $accountProduct,
            before: ['name' => $name, 'code' => $code],
            description: "Account product '{$name}' ({$code}) was deleted by '{$actor?->username}'.",
        );
    }

    private function assertGeneralLedgerMappingsAreValid(array $mappings, Currency $productCurrency, bool $allowOverdraft = false): void
    {
        $providedTypes = collect($mappings)->pluck('financial_account_type');

        foreach ($providedTypes as $name) {
            if (! in_array($name, AccountProductType::names(), true)) {
                throw ValidationException::withMessages([
                    'general_ledgers' => ["'{$name}' is not a valid financial account type for account products."],
                ]);
            }
        }

        foreach ($this->requiredFinancialAccountTypes($allowOverdraft) as $type) {
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

    /**
     * @return AccountProductType[]
     */
    private function requiredFinancialAccountTypes(bool $allowOverdraft = false): array
    {
        $required = AccountProductType::required();

        if ($allowOverdraft) {
            $required[] = AccountProductType::OVERDRAFT_PORTFOLIO_CONTROL;
        }

        return $required;
    }

    private function generateProductCode(string $productType): string
    {
        $prefix = self::PRODUCT_TYPE_PREFIXES[$productType];

        $lastSequence = AccountProduct::where('code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = $lastSequence ? ((int) substr($lastSequence, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
