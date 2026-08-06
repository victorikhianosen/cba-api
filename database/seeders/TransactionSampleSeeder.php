<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Models\Transaction;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Demo data only — writes straight into financial_transactions/transactions to show off
// the full taxonomy (interbank, intrabank, VAS, card, POS, ATM, loan, investment, interest,
// charges, reversal) without going through the real posting/approval/balance-check flow.
// Not something a real money-movement flow should ever bypass like this.
class TransactionSampleSeeder extends Seeder
{
    private int $currencyId;

    /** @var array<int, array{balance: string}> */
    private array $runningBalance = [];

    public function run(): void
    {
        $this->currencyId = (int) (\App\Models\Currency::where('code', 'NGN')->value('id') ?? 1);

        $accounts = Account::where('status', 'active')->where('currency_id', $this->currencyId)->get();

        if ($accounts->isEmpty()) {
            $this->command?->warn('No active NGN accounts found — skipping TransactionSampleSeeder.');
            return;
        }

        foreach ($accounts as $account) {
            $this->runningBalance[$account->id] = (string) $account->account_balance;
        }

        $scenarios = $this->scenarios($accounts);

        foreach ($scenarios as $scenario) {
            $this->postScenario($scenario);
        }

        // Reflect the seeded activity on the account rows themselves so the account list/
        // detail pages show balances consistent with the transactions just created.
        foreach ($this->runningBalance as $accountId => $balance) {
            Account::whereKey($accountId)->update([
                'account_balance'   => $balance,
                'available_balance' => $balance,
            ]);
        }

        $this->command?->info('Seeded ' . count($scenarios) . ' sample transactions across ' . $accounts->count() . ' accounts.');
    }

    /**
     * @param \Illuminate\Support\Collection<int, Account> $accounts
     * @return array<int, array<string, mixed>>
     */
    private function scenarios($accounts): array
    {
        $a = fn (int $index) => $accounts[$index % $accounts->count()];

        return [
            // ── Interbank transfers (NIP, to/from other Nigerian banks) ──
            [
                'account' => $a(0), 'type' => 'debit', 'amount' => '15000',
                'module' => 'transfers', 'transaction_type' => 'interbank_transfer', 'business_service' => 'NIP Transfer',
                'channel' => 'mobile', 'service_provider' => 'NIBSS', 'provider_reference' => 'NIP' . random_int(100000000, 999999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Ifeoma Okafor', 'counterparty_account_number' => '8021234567',
                'counterparty_bank_code' => '100004', 'counterparty_bank_name' => 'Opay',
                'narration' => 'Transfer to Opay - Ifeoma Okafor',
            ],
            [
                'account' => $a(1), 'type' => 'credit', 'amount' => '45000',
                'module' => 'transfers', 'transaction_type' => 'interbank_transfer', 'business_service' => 'NIP Transfer',
                'channel' => 'internet_banking', 'service_provider' => 'NIBSS', 'provider_reference' => 'NIP' . random_int(100000000, 999999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Chidi Nwosu', 'counterparty_account_number' => '0123456789',
                'counterparty_bank_code' => '000058', 'counterparty_bank_name' => 'GTBank',
                'narration' => 'Inflow from GTBank - Chidi Nwosu',
            ],
            [
                'account' => $a(2), 'type' => 'debit', 'amount' => '8500',
                'module' => 'transfers', 'transaction_type' => 'interbank_transfer', 'business_service' => 'NIP Transfer',
                'channel' => 'mobile', 'service_provider' => 'NIBSS', 'provider_reference' => 'NIP' . random_int(100000000, 999999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Blessing Eze', 'counterparty_account_number' => '9051112233',
                'counterparty_bank_code' => '100033', 'counterparty_bank_name' => 'PalmPay',
                'narration' => 'Transfer to PalmPay - Blessing Eze',
            ],
            [
                'account' => $a(0), 'type' => 'credit', 'amount' => '75000',
                'module' => 'transfers', 'transaction_type' => 'interbank_transfer', 'business_service' => 'NIP Transfer',
                'channel' => 'mobile', 'service_provider' => 'NIBSS', 'provider_reference' => 'NIP' . random_int(100000000, 999999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Adewale Ogundipe', 'counterparty_account_number' => '2034567891',
                'counterparty_bank_code' => '000015', 'counterparty_bank_name' => 'Zenith Bank',
                'narration' => 'Inflow from Zenith Bank - Adewale Ogundipe',
            ],
            [
                'account' => $a(1), 'type' => 'credit', 'amount' => '120000',
                'module' => 'transfers', 'transaction_type' => 'interbank_transfer', 'business_service' => 'NIP Transfer',
                'channel' => 'internet_banking', 'service_provider' => 'NIBSS', 'provider_reference' => 'NIP' . random_int(100000000, 999999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Ngozi Adeyemi', 'counterparty_account_number' => '3078812345',
                'counterparty_bank_code' => '000010', 'counterparty_bank_name' => 'Ecobank',
                'narration' => 'Inflow from Ecobank - Ngozi Adeyemi',
            ],

            // ── Intrabank transfer (same bank, another customer's account) ──
            [
                'account' => $a(3), 'type' => 'debit', 'amount' => '20000',
                'module' => 'transfers', 'transaction_type' => 'intrabank_transfer', 'business_service' => 'Internal Transfer',
                'channel' => 'mobile', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'account', 'counterparty_id' => $a(4)->id,
                'counterparty_name' => $a(4)->account_name, 'counterparty_account_number' => $a(4)->account_number,
                'narration' => 'Transfer to ' . $a(4)->account_name,
            ],
            [
                'account' => $a(4), 'type' => 'credit', 'amount' => '20000',
                'module' => 'transfers', 'transaction_type' => 'intrabank_transfer', 'business_service' => 'Internal Transfer',
                'channel' => 'mobile', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'account', 'counterparty_id' => $a(3)->id,
                'counterparty_name' => $a(3)->account_name, 'counterparty_account_number' => $a(3)->account_number,
                'narration' => 'Transfer from ' . $a(3)->account_name,
            ],

            // ── Bills / VAS ──
            [
                'account' => $a(0), 'type' => 'debit', 'amount' => '10500',
                'module' => 'bills', 'transaction_type' => 'cable_tv', 'business_service' => 'DSTV Compact Plus',
                'channel' => 'mobile', 'service_provider' => 'Baxi', 'provider_reference' => 'BXI' . random_int(1000000, 9999999),
                'counterparty_type' => 'provider', 'counterparty_name' => 'DSTV / MultiChoice',
                'narration' => 'DSTV Compact Plus subscription',
            ],
            [
                'account' => $a(1), 'type' => 'debit', 'amount' => '7500',
                'module' => 'bills', 'transaction_type' => 'electricity', 'business_service' => 'Ikeja Electric Prepaid',
                'channel' => 'internet_banking', 'service_provider' => 'Interswitch', 'provider_reference' => 'TKN' . random_int(10000000, 99999999),
                'counterparty_type' => 'provider', 'counterparty_name' => 'Ikeja Electric (IKEDC)',
                'narration' => 'Electricity token purchase - IKEDC prepaid meter',
                'metadata' => ['token' => (string) random_int(1000000000000, 9999999999999), 'meter_number' => '04512233445'],
            ],
            [
                'account' => $a(2), 'type' => 'debit', 'amount' => '9000',
                'module' => 'bills', 'transaction_type' => 'electricity', 'business_service' => 'Eko Electric Prepaid',
                'channel' => 'mobile', 'service_provider' => 'Baxi', 'provider_reference' => 'TKN' . random_int(10000000, 99999999),
                'counterparty_type' => 'provider', 'counterparty_name' => 'Eko Electric (EKEDC)',
                'narration' => 'Electricity token purchase - EKEDC prepaid meter',
                'metadata' => ['token' => (string) random_int(1000000000000, 9999999999999), 'meter_number' => '02233445566'],
            ],
            [
                'account' => $a(3), 'type' => 'debit', 'amount' => '1000',
                'module' => 'bills', 'transaction_type' => 'airtime', 'business_service' => 'MTN Airtime',
                'channel' => 'ussd', 'service_provider' => 'VTPass', 'provider_reference' => 'VTP' . random_int(1000000, 9999999),
                'counterparty_type' => 'provider', 'counterparty_name' => 'MTN Nigeria',
                'narration' => 'MTN airtime recharge - 08031234567',
            ],
            [
                'account' => $a(4), 'type' => 'debit', 'amount' => '2500',
                'module' => 'bills', 'transaction_type' => 'data', 'business_service' => 'Glo 5GB Data Bundle',
                'channel' => 'mobile', 'service_provider' => 'VTPass', 'provider_reference' => 'VTP' . random_int(1000000, 9999999),
                'counterparty_type' => 'provider', 'counterparty_name' => 'Globacom (Glo)',
                'narration' => 'Glo data bundle - 08051234567',
            ],
            [
                'account' => $a(0), 'type' => 'debit', 'amount' => '5000',
                'module' => 'bills', 'transaction_type' => 'betting', 'business_service' => 'Bet9ja Wallet Funding',
                'channel' => 'mobile', 'service_provider' => 'Quickteller', 'provider_reference' => 'QTF' . random_int(1000000, 9999999),
                'counterparty_type' => 'provider', 'counterparty_name' => 'Bet9ja',
                'narration' => 'Bet9ja wallet funding',
            ],

            // ── Card / POS / ATM ──
            [
                'account' => $a(1), 'type' => 'debit', 'amount' => '32000',
                'module' => 'card', 'transaction_type' => 'pos_purchase', 'business_service' => 'Shoprite POS Purchase',
                'channel' => 'pos', 'service_provider' => 'Interswitch', 'provider_reference' => 'POS' . random_int(100000, 999999),
                'counterparty_type' => 'merchant', 'counterparty_name' => 'Shoprite Nigeria',
                'narration' => 'POS purchase at Shoprite',
            ],
            [
                'account' => $a(2), 'type' => 'debit', 'amount' => '20000',
                'module' => 'card', 'transaction_type' => 'atm_withdrawal', 'business_service' => 'ATM Cash Withdrawal',
                'channel' => 'atm', 'service_provider' => 'NIBSS', 'provider_reference' => 'ATM' . random_int(100000, 999999),
                'counterparty_type' => 'merchant', 'counterparty_name' => 'GTBank ATM - Ikeja',
                'narration' => 'ATM withdrawal',
            ],
            [
                'account' => $a(3), 'type' => 'debit', 'amount' => '55000',
                'module' => 'card', 'transaction_type' => 'card_payment', 'business_service' => 'Jumia Online Purchase',
                'channel' => 'web', 'service_provider' => 'Interswitch', 'provider_reference' => 'CRD' . random_int(1000000, 9999999),
                'counterparty_type' => 'merchant', 'counterparty_name' => 'Jumia Nigeria',
                'narration' => 'Online card payment - Jumia',
            ],
            [
                'account' => $a(4), 'type' => 'credit', 'amount' => '18000',
                'module' => 'card', 'transaction_type' => 'card_refund', 'business_service' => 'Jumia Refund',
                'channel' => 'web', 'service_provider' => 'Interswitch', 'provider_reference' => 'RFD' . random_int(1000000, 9999999),
                'counterparty_type' => 'merchant', 'counterparty_name' => 'Jumia Nigeria',
                'narration' => 'Refund for cancelled order',
            ],

            // ── Loans ──
            [
                'account' => $a(0), 'type' => 'credit', 'amount' => '250000',
                'module' => 'loans', 'transaction_type' => 'loan_disbursement', 'business_service' => 'Personal Loan Disbursement',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Loans Portfolio',
                'narration' => 'Personal loan disbursement',
            ],
            [
                'account' => $a(1), 'type' => 'debit', 'amount' => '25000',
                'module' => 'loans', 'transaction_type' => 'loan_repayment', 'business_service' => 'Personal Loan Repayment',
                'channel' => 'mobile', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Loans Portfolio',
                'narration' => 'Monthly loan repayment - installment 3/12',
            ],
            [
                'account' => $a(2), 'type' => 'credit', 'amount' => '100000',
                'module' => 'loans', 'transaction_type' => 'loan_drawdown', 'business_service' => 'Business Credit Line Drawdown',
                'channel' => 'internet_banking', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Credit Line Facility',
                'narration' => 'Drawdown against approved business credit line',
            ],
            [
                'account' => $a(3), 'type' => 'debit', 'amount' => '3500',
                'module' => 'loans', 'transaction_type' => 'loan_penalty', 'business_service' => 'Late Repayment Penalty',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Loans Portfolio',
                'narration' => 'Penalty fee for late loan repayment',
            ],

            // ── Investments ──
            [
                'account' => $a(2), 'type' => 'debit', 'amount' => '500000',
                'module' => 'investments', 'transaction_type' => 'investment_placement', 'business_service' => 'Fixed Deposit - 90 Days',
                'channel' => 'internet_banking', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Fixed Deposit Portfolio',
                'narration' => 'Fixed deposit placement - 90 days @ 12% p.a.',
            ],
            [
                'account' => $a(3), 'type' => 'credit', 'amount' => '515000',
                'module' => 'investments', 'transaction_type' => 'investment_maturity', 'business_service' => 'Fixed Deposit Maturity',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Fixed Deposit Portfolio',
                'narration' => 'Fixed deposit maturity payout (principal + interest)',
            ],
            [
                'account' => $a(4), 'type' => 'debit', 'amount' => '150000',
                'module' => 'investments', 'transaction_type' => 'investment_topup', 'business_service' => 'Mutual Fund Top-Up',
                'channel' => 'mobile', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Mutual Fund Portfolio',
                'narration' => 'Additional contribution to mutual fund',
            ],
            [
                'account' => $a(0), 'type' => 'credit', 'amount' => '92000',
                'module' => 'investments', 'transaction_type' => 'investment_withdrawal', 'business_service' => 'Mutual Fund Early Liquidation',
                'channel' => 'internet_banking', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Mutual Fund Portfolio',
                'narration' => 'Early withdrawal from mutual fund (before maturity, less exit fee)',
            ],

            // ── Interest & charges ──
            [
                'account' => $a(4), 'type' => 'credit', 'amount' => '1250',
                'module' => 'interest', 'transaction_type' => 'interest_credit', 'business_service' => 'Savings Interest',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Interest Expense',
                'narration' => 'Monthly savings interest credit',
            ],
            [
                'account' => $a(0), 'type' => 'debit', 'amount' => '52.50',
                'module' => 'charges', 'transaction_type' => 'sms_alert_charge', 'business_service' => 'SMS Alert Fee',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Fee Income',
                'narration' => 'Quarterly SMS alert charge',
            ],
            [
                'account' => $a(1), 'type' => 'debit', 'amount' => '1.50',
                'module' => 'charges', 'transaction_type' => 'stamp_duty', 'business_service' => 'Stamp Duty (CBN)',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'general_ledger', 'counterparty_name' => 'Stamp Duty Payable',
                'narration' => 'Stamp duty on electronic transfer',
            ],

            // ── Cash / salary / reversal ──
            [
                'account' => $a(2), 'type' => 'credit', 'amount' => '350000',
                'module' => 'deposits', 'transaction_type' => 'salary_credit', 'business_service' => 'Salary Payment',
                'channel' => 'api', 'service_provider' => null, 'provider_reference' => 'PAYROLL' . random_int(100000, 999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Zenith Bank - Employer Payroll',
                'counterparty_bank_code' => '000015', 'counterparty_bank_name' => 'Zenith Bank',
                'narration' => 'Salary payment - July 2026',
            ],
            [
                'account' => $a(3), 'type' => 'credit', 'amount' => '100000',
                'module' => 'deposits', 'transaction_type' => 'cash_deposit', 'business_service' => 'Over-the-Counter Deposit',
                'channel' => 'admin', 'service_provider' => null, 'provider_reference' => null,
                'counterparty_type' => 'external', 'counterparty_name' => 'Cash Deposit - Branch Teller',
                'narration' => 'Cash deposit at Ikeja branch',
            ],
            [
                'account' => $a(4), 'type' => 'credit', 'amount' => '5000',
                'module' => 'transfers', 'transaction_type' => 'reversal', 'business_service' => 'Failed Transfer Reversal',
                'channel' => 'admin', 'service_provider' => 'NIBSS', 'provider_reference' => 'REV' . random_int(1000000, 9999999),
                'counterparty_type' => 'external_bank', 'counterparty_name' => 'Reversal - Failed NIP Transfer',
                'narration' => 'Reversal of failed interbank transfer',
            ],
        ];
    }

    private function postScenario(array $s): void
    {
        /** @var Account $account */
        $account = $s['account'];
        $amount  = (string) $s['amount'];

        $financialTransaction = FinancialTransaction::create([
            'reference'             => ReferenceGenerator::generate('FRN'),
            'transaction_reference' => ReferenceGenerator::generate('TRN'),
            'idempotency_key'       => (string) Str::uuid(),
            'module'                => $s['module'],
            'transaction_type'      => $s['transaction_type'],
            'business_service'      => $s['business_service'],
            'channel'               => $s['channel'],
            'service_provider'      => $s['service_provider'] ?? null,
            'provider_reference'    => $s['provider_reference'] ?? null,
            'customer_id'           => $account->customer_id,
            'amount'                => $amount,
            'fee'                   => '0',
            'commission'            => '0',
            'vat'                   => '0',
            'discount'              => '0',
            'currency_id'           => $this->currencyId,
            'exchange_rate'         => '1.00000000',
            'status'                => 'completed',
            'narration'             => $s['narration'],
            'metadata'              => $s['metadata'] ?? null,
            'processed_at'          => now(),
            'completed_at'          => now(),
        ]);

        $balanceBefore = $this->runningBalance[$account->id];
        $balanceAfter  = $s['type'] === 'debit'
            ? bcsub($balanceBefore, $amount, 4)
            : bcadd($balanceBefore, $amount, 4);

        $this->runningBalance[$account->id] = $balanceAfter;

        Transaction::create([
            'financial_transaction_id'    => $financialTransaction->id,
            'entry_group_id'               => (string) Str::uuid(),
            'transaction_reference'        => $financialTransaction->transaction_reference,
            'reference'                    => ReferenceGenerator::generate('TXN'),
            'account_id'                   => $account->id,
            'customer_id'                  => $account->customer_id,
            'module'                       => $s['module'],
            'transaction_type'             => $s['transaction_type'],
            'transaction_subtype'          => $s['transaction_subtype'] ?? null,
            'business_service'             => $s['business_service'],
            'channel'                      => $s['channel'],
            'service_provider'             => $s['service_provider'] ?? null,
            'provider_reference'           => $s['provider_reference'] ?? null,
            'counterparty_type'            => $s['counterparty_type'] ?? null,
            'counterparty_id'              => $s['counterparty_id'] ?? null,
            'counterparty_name'            => $s['counterparty_name'] ?? null,
            'counterparty_account_number'  => $s['counterparty_account_number'] ?? null,
            'counterparty_bank_code'       => $s['counterparty_bank_code'] ?? null,
            'counterparty_bank_name'       => $s['counterparty_bank_name'] ?? null,
            'type'                         => $s['type'],
            'direction'                    => $s['type'] === 'credit' ? 'inward' : 'outward',
            'amount'                       => $amount,
            'balance_before'               => $balanceBefore,
            'balance_after'                => $balanceAfter,
            'currency_id'                  => $this->currencyId,
            'value_date'                   => now()->toDateString(),
            'posting_date'                 => now()->toDateString(),
            'status'                       => 'completed',
            'narration'                    => $s['narration'],
            'metadata'                     => $s['metadata'] ?? null,
        ]);
    }
}
