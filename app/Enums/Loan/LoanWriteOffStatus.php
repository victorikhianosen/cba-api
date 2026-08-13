<?php

declare(strict_types=1);

namespace App\Enums\Loan;

// Mirrors InvestmentPartialLiquidationStatus exactly — same maker->checker->TOTP shape.
enum LoanWriteOffStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
