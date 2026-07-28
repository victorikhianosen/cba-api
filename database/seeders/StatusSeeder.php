<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->statuses() as $status) {
            Status::updateOrCreate(['code' => $status['code']], $status);
        }
    }

    /** @return array<int, array{code: string, name: string, category: string, description: string}> */
    private function statuses(): array
    {
        return [
            ['code' => 'pending', 'name' => 'Pending', 'category' => 'Account', 'description' => 'Account has been created but is awaiting approval or activation.'],
            ['code' => 'active', 'name' => 'Active', 'category' => 'Account', 'description' => 'Account is fully operational and can perform transactions.'],
            ['code' => 'inactive', 'name' => 'Inactive', 'category' => 'Account', 'description' => 'Account is temporarily not in use; transactions may be restricted.'],
            ['code' => 'dormant', 'name' => 'Dormant', 'category' => 'Account', 'description' => 'No customer-initiated activity for a defined period.'],
            ['code' => 'frozen', 'name' => 'Frozen', 'category' => 'Account', 'description' => 'Transactions are blocked due to regulatory, legal, or customer request.'],
            ['code' => 'blocked', 'name' => 'Blocked', 'category' => 'Account', 'description' => 'Account cannot perform debit, credit, or both due to system or business rules.'],
            ['code' => 'restricted', 'name' => 'Restricted', 'category' => 'Account', 'description' => 'Limited transactions are allowed, such as credits only or debits only.'],
            ['code' => 'suspended', 'name' => 'Suspended', 'category' => 'Account', 'description' => 'Account operations are temporarily halted pending investigation or compliance checks.'],
            ['code' => 'under_review', 'name' => 'Under Review', 'category' => 'Account', 'description' => 'Account is undergoing manual verification or compliance review.'],
            ['code' => 'kyc_pending', 'name' => 'KYC Pending', 'category' => 'KYC', 'description' => 'Customer has not completed Know Your Customer requirements.'],
            ['code' => 'kyc_rejected', 'name' => 'KYC Rejected', 'category' => 'KYC', 'description' => 'Submitted KYC documents were rejected.'],
            ['code' => 'kyc_verified', 'name' => 'KYC Verified', 'category' => 'KYC', 'description' => 'KYC requirements have been successfully completed.'],
            ['code' => 'lien', 'name' => 'Lien', 'category' => 'Account', 'description' => 'Funds are held due to a legal, loan, or court order.'],
            ['code' => 'deceased', 'name' => 'Deceased', 'category' => 'Customer', 'description' => 'Account holder is confirmed deceased; account is handled according to policy.'],
            ['code' => 'closed', 'name' => 'Closed', 'category' => 'Account', 'description' => 'Account has been permanently closed.'],
            ['code' => 'closed_pending_settlement', 'name' => 'Closed Pending Settlement', 'category' => 'Account', 'description' => 'Closure requested but outstanding balances or obligations remain.'],
            ['code' => 'written_off', 'name' => 'Written Off', 'category' => 'Loan', 'description' => 'Used mainly for loan accounts where recovery is no longer expected.'],
            ['code' => 'archived', 'name' => 'Archived', 'category' => 'System', 'description' => 'Historical account retained for recordkeeping; no transactions allowed.'],
            ['code' => 'blacklisted', 'name' => 'Blacklisted', 'category' => 'Compliance', 'description' => 'Account is prohibited due to fraud or regulatory reasons.'],
            ['code' => 'fraud_hold', 'name' => 'Fraud Hold', 'category' => 'Compliance', 'description' => 'Account is under investigation for suspected fraud.'],
            ['code' => 'escheated', 'name' => 'Escheated', 'category' => 'Regulatory', 'description' => 'Account transferred to government due to prolonged inactivity, where applicable.'],
        ];
    }
}
