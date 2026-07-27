<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountTier;
use Illuminate\Database\Seeder;

// CBN's tiered KYC framework for Nigerian bank accounts — limits in Naira.
class AccountTierSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTier([
            'level'                          => 1,
            'description'                    => 'Tier 1 — minimal KYC (name, phone, date of birth, address, passport photo). Suited for low-value, low-risk accounts.',
            'daily_transaction_limit'        => 50_000,
            'daily_withdrawal_limit'         => 50_000,
            'daily_transfer_limit'           => 50_000,
            'max_account_balance'            => 300_000,
            'max_single_transaction_amount'  => 50_000,
            'max_daily_inflow'               => 50_000,
            'max_daily_outflow'              => 50_000,
            'require_bvn'                    => false,
            'require_nin'                    => false,
            'require_address_verification'   => false,
            'require_face_verification'      => false,
        ]);

        $this->seedTier([
            'level'                          => 2,
            'description'                    => 'Tier 2 — full KYC with BVN and verified address, no in-person verification.',
            'daily_transaction_limit'        => 200_000,
            'daily_withdrawal_limit'         => 200_000,
            'daily_transfer_limit'           => 200_000,
            'max_account_balance'            => 500_000,
            'max_single_transaction_amount'  => 200_000,
            'max_daily_inflow'               => 200_000,
            'max_daily_outflow'              => 200_000,
            'require_bvn'                    => true,
            'require_nin'                    => false,
            'require_address_verification'   => true,
            'require_face_verification'      => false,
        ]);

        $this->seedTier([
            'level'                          => 3,
            'description'                    => 'Tier 3 — full KYC with BVN, NIN, verified address and face verification. No cumulative balance limit.',
            'daily_transaction_limit'        => 5_000_000,
            'daily_withdrawal_limit'         => 5_000_000,
            'daily_transfer_limit'           => 5_000_000,
            'max_account_balance'            => null,
            'max_single_transaction_amount'  => 5_000_000,
            'max_daily_inflow'               => 5_000_000,
            'max_daily_outflow'              => 5_000_000,
            'require_bvn'                    => true,
            'require_nin'                    => true,
            'require_address_verification'   => true,
            'require_face_verification'      => true,
        ]);
    }

    private function seedTier(array $attributes): void
    {
        AccountTier::updateOrCreate(
            ['level' => $attributes['level']],
            $attributes + ['status' => 'active'],
        );
    }
}
