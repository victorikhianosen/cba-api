<?php

namespace App\Enums;

/**
 * Implemented by every product family's financial-account-type enum
 * (SavingsProductFinancialAccountType, LoanProductFinancialAccountType, ...)
 * so the service/resource layer can resolve GL roles generically without
 * hardcoding a specific enum class.
 */
interface FinancialAccountTypeContract
{
    public function label(): string;

    public static function fromName(string $name): self;

    /**
     * @return string[]
     */
    public static function names(): array;

    /**
     * Roles that are unconditionally mandatory for this product family.
     * Some enums may have additional roles that are valid but optional
     * (or conditionally required based on product data, handled outside
     * the enum) — those are still present in cases()/names() but excluded
     * here.
     *
     * @return self[]
     */
    public static function required(): array;
}
