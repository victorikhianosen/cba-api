<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Bulk demo data — 40,000 transactions spread across every category in the taxonomy
// (transfers, bills/VAS, card/POS/ATM, loans, investments, interest, charges, deposits),
// randomized amounts/dates/counterparties. Writes straight into financial_transactions/
// transactions with raw batched inserts (not Eloquent::create) so 40k rows seed in seconds
// instead of minutes. Same bypass-the-real-flow caveat as TransactionSampleSeeder: this is
// for populating a demo/staging list view, not something a real money-movement path should
// ever do.
class TransactionBulkSeeder extends Seeder
{
    private const TOTAL_RECORDS = 40000;
    private const CHUNK_SIZE = 1000;
    private const DAYS_BACK = 365;

    private int $currencyId;

    /** @var array<int, array{id:int, account_number:string, account_name:string, customer_id:?int, balance:string}> */
    private array $accounts = [];

    public function run(): void
    {
        $this->currencyId = (int) (\App\Models\Currency::where('code', 'NGN')->value('id') ?? 1);

        $accounts = Account::where('status', 'active')->where('currency_id', $this->currencyId)->get();

        if ($accounts->isEmpty()) {
            $this->command?->warn('No active NGN accounts found — skipping TransactionBulkSeeder.');
            return;
        }

        foreach ($accounts as $account) {
            $this->accounts[$account->id] = [
                'id'             => $account->id,
                'account_number' => $account->account_number,
                'account_name'   => $account->account_name,
                'customer_id'    => $account->customer_id,
                'balance'        => (string) $account->account_balance,
            ];
        }

        $faker = \Faker\Factory::create('en_NG');

        // Build every record's attributes in memory first (cheap — no DB round trips),
        // grouped by account so balances can be walked forward chronologically per account.
        $records = [];
        for ($i = 0; $i < self::TOTAL_RECORDS; $i++) {
            $accountId = array_rand($this->accounts);
            $records[] = $this->buildRecord($faker, $accountId);
        }

        foreach ($records as &$record) {
            $record['_account_id'] = $record['account_id'];
        }
        unset($record);

        usort($records, fn ($a, $b) => $a['_sort_date'] <=> $b['_sort_date']);

        $grouped = [];
        foreach ($records as $record) {
            $grouped[$record['_account_id']][] = $record;
        }

        $ordered = [];
        foreach ($grouped as $accountId => $accountRecords) {
            $balance = $this->accounts[$accountId]['balance'];

            foreach ($accountRecords as $record) {
                $balanceBefore = $balance;
                $balance = $record['type'] === 'debit'
                    ? bcsub($balance, $record['amount'], 4)
                    : bcadd($balance, $record['amount'], 4);

                $record['balance_before'] = $balanceBefore;
                $record['balance_after']  = $balance;
                $ordered[] = $record;
            }

            $this->accounts[$accountId]['balance'] = $balance;
        }

        // Re-sort into chronological order overall so the ids/insert order read naturally,
        // then batch-insert both tables in lockstep chunks.
        usort($ordered, fn ($a, $b) => $a['_sort_date'] <=> $b['_sort_date']);

        $inserted = 0;
        foreach (array_chunk($ordered, self::CHUNK_SIZE) as $chunk) {
            $this->insertChunk($chunk);
            $inserted += count($chunk);
            $this->command?->info("Seeded {$inserted}/" . self::TOTAL_RECORDS . ' transactions...');
        }

        foreach ($this->accounts as $accountId => $account) {
            Account::whereKey($accountId)->update([
                'account_balance'   => $account['balance'],
                'available_balance' => $account['balance'],
            ]);
        }

        $this->command?->info('Seeded ' . self::TOTAL_RECORDS . ' transactions across ' . count($this->accounts) . ' accounts.');
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     */
    private function insertChunk(array $chunk): void
    {
        DB::transaction(function () use ($chunk) {
            $financialRows = [];
            foreach ($chunk as $record) {
                $financialRows[] = [
                    'transaction_reference' => $record['transaction_reference'],
                    'idempotency_key'       => (string) Str::uuid(),
                    'module'                => $record['module'],
                    'transaction_type'      => $record['transaction_type'],
                    'business_service'      => $record['business_service'],
                    'channel'               => $record['channel'],
                    'service_provider'      => $record['service_provider'],
                    'provider_reference'    => $record['provider_reference'],
                    'customer_id'           => $record['customer_id'],
                    'amount'                => $record['amount'],
                    'fee'                   => '0',
                    'commission'            => '0',
                    'vat'                   => '0',
                    'discount'              => '0',
                    'currency_id'           => $this->currencyId,
                    'exchange_rate'         => '1.00000000',
                    'status'                => $record['status'],
                    'narration'             => $record['narration'],
                    'metadata'              => $record['metadata'] ? json_encode($record['metadata']) : null,
                    'processed_at'          => $record['posting_date'],
                    'completed_at'          => $record['status'] === 'completed' ? $record['posting_date'] : null,
                    'created_at'            => $record['posting_date'],
                    'updated_at'            => $record['posting_date'],
                ];
            }

            // insertGetId on the first row gives us the real assigned id (LAST_INSERT_ID()),
            // which is safe even after a prior rollback advanced the auto-increment counter
            // past max(id). MySQL then allocates the rest of this single multi-row INSERT as
            // a contiguous block right after it (innodb_autoinc_lock_mode default), so the
            // remaining ids are guaranteed to be firstId+1, firstId+2, ...
            $firstId = DB::table('financial_transactions')->insertGetId($financialRows[0]);
            if (count($financialRows) > 1) {
                DB::table('financial_transactions')->insert(array_slice($financialRows, 1));
            }
            $transactionRows = [];
            foreach ($chunk as $index => $record) {
                $transactionRows[] = [
                    'financial_transaction_id'    => $firstId + $index,
                    'entry_group_id'              => (string) Str::uuid(),
                    'transaction_reference'       => $record['transaction_reference'],
                    'reference'                   => $record['reference'],
                    'account_id'                  => $record['account_id'],
                    'customer_id'                 => $record['customer_id'],
                    'type'                        => $record['type'],
                    'module'                      => $record['module'],
                    'transaction_type'            => $record['transaction_type'],
                    'transaction_subtype'         => $record['transaction_subtype'],
                    'business_service'            => $record['business_service'],
                    'channel'                     => $record['channel'],
                    'service_provider'            => $record['service_provider'],
                    'provider_reference'          => $record['provider_reference'],
                    'counterparty_type'           => $record['counterparty_type'],
                    'counterparty_id'             => $record['counterparty_id'],
                    'counterparty_name'           => $record['counterparty_name'],
                    'counterparty_account_number' => $record['counterparty_account_number'],
                    'counterparty_bank_code'      => $record['counterparty_bank_code'],
                    'counterparty_bank_name'      => $record['counterparty_bank_name'],
                    'direction'                   => $record['type'] === 'credit' ? 'inward' : 'outward',
                    'amount'                      => $record['amount'],
                    'balance_before'              => $record['balance_before'],
                    'balance_after'               => $record['balance_after'],
                    'currency_id'                 => $this->currencyId,
                    'value_date'                  => $record['posting_date']->toDateString(),
                    'posting_date'                => $record['posting_date']->toDateString(),
                    'status'                      => $record['status'],
                    'narration'                   => $record['narration'],
                    'metadata'                    => $record['metadata'] ? json_encode($record['metadata']) : null,
                    'created_at'                  => $record['posting_date'],
                    'updated_at'                  => $record['posting_date'],
                ];
            }

            DB::table('transactions')->insert($transactionRows);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecord(\Faker\Generator $faker, int $accountId): array
    {
        $account  = $this->accounts[$accountId];
        $category = $this->categories()[array_rand($this->categories())];

        $type   = $category['type'] === 'random' ? $faker->randomElement(['debit', 'credit']) : $category['type'];
        $amount = (string) $faker->randomFloat(2, $category['amount'][0], $category['amount'][1]);

        $postingDate = \Carbon\Carbon::now()->subDays($faker->numberBetween(0, self::DAYS_BACK))
            ->subMinutes($faker->numberBetween(0, 1440));

        $status = $faker->randomElement(['completed', 'completed', 'completed', 'completed', 'pending', 'failed', 'reversed']);

        [$counterpartyType, $counterpartyId, $counterpartyName, $counterpartyAccountNumber, $counterpartyBankCode, $counterpartyBankName]
            = $this->buildCounterparty($faker, $category, $account);

        $businessService = is_array($category['business_service'])
            ? $faker->randomElement($category['business_service'])
            : $category['business_service'];

        $providerReference = $category['service_provider']
            ? strtoupper(Str::random(3)) . $faker->numerify('########')
            : null;

        return [
            'account_id'                   => $accountId,
            'customer_id'                  => $account['customer_id'],
            'reference'                    => ReferenceGenerator::generate('TXN'),
            'transaction_reference'        => ReferenceGenerator::generate('TRN'),
            'type'                         => $type,
            'amount'                       => $amount,
            'module'                       => $category['module'],
            'transaction_type'             => $category['transaction_type'],
            'transaction_subtype'          => $category['transaction_subtype'] ?? null,
            'business_service'             => $businessService,
            'channel'                      => $faker->randomElement($category['channel']),
            'service_provider'             => $category['service_provider'],
            'provider_reference'           => $providerReference,
            'counterparty_type'            => $counterpartyType,
            'counterparty_id'              => $counterpartyId,
            'counterparty_name'            => $counterpartyName,
            'counterparty_account_number'  => $counterpartyAccountNumber,
            'counterparty_bank_code'       => $counterpartyBankCode,
            'counterparty_bank_name'       => $counterpartyBankName,
            'status'                       => $status,
            'narration'                    => $this->buildNarration($category, $businessService, $counterpartyName),
            'metadata'                     => $category['metadata'] ? $category['metadata']($faker) : null,
            'posting_date'                 => $postingDate,
            '_sort_date'                   => $postingDate->timestamp,
        ];
    }

    private function buildNarration(array $category, string $businessService, ?string $counterpartyName): string
    {
        return match ($category['transaction_type']) {
            'interbank_transfer' => ($category['type'] === 'credit' ? 'Inflow from ' : 'Transfer to ') . ($counterpartyName ?? 'external account'),
            'intrabank_transfer' => ($category['type'] === 'credit' ? 'Transfer from ' : 'Transfer to ') . ($counterpartyName ?? 'internal account'),
            default => "{$businessService}" . ($counterpartyName ? " - {$counterpartyName}" : ''),
        };
    }

    /**
     * @return array{0: ?string, 1: ?int, 2: ?string, 3: ?string, 4: ?string, 5: ?string}
     */
    private function buildCounterparty(\Faker\Generator $faker, array $category, array $account): array
    {
        return match ($category['counterparty_type']) {
            'external_bank' => [
                'external_bank',
                null,
                $faker->name(),
                $faker->numerify('##########'),
                $faker->randomElement(['000004', '000058', '000015', '000010', '100004', '100033']),
                $faker->randomElement(['GTBank', 'Zenith Bank', 'Access Bank', 'Ecobank', 'Opay', 'PalmPay', 'Kuda', 'Moniepoint']),
            ],
            'account' => (function () use ($account) {
                $others = array_diff_key($this->accounts, [$account['id'] => true]);
                if (empty($others)) {
                    return ['account', null, $account['account_name'], $account['account_number'], null, null];
                }
                $other = $others[array_rand($others)];
                return ['account', $other['id'], $other['account_name'], $other['account_number'], null, null];
            })(),
            'provider' => ['provider', null, $category['business_service_name'] ?? $faker->company(), null, null, null],
            'merchant' => ['merchant', null, $faker->company(), null, null, null],
            'general_ledger' => ['general_ledger', null, $category['gl_name'] ?? 'General Ledger', null, null, null],
            'external' => ['external', null, $category['gl_name'] ?? 'Branch Teller', null, null, null],
            default => [null, null, null, null, null, null],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return [
            [
                'module' => 'transfers', 'transaction_type' => 'interbank_transfer',
                'business_service' => 'NIP Transfer', 'channel' => ['mobile', 'internet_banking', 'ussd'],
                'service_provider' => 'NIBSS', 'counterparty_type' => 'external_bank',
                'type' => 'random', 'amount' => [500, 500000], 'metadata' => null,
            ],
            [
                'module' => 'transfers', 'transaction_type' => 'intrabank_transfer',
                'business_service' => 'Internal Transfer', 'channel' => ['mobile', 'internet_banking'],
                'service_provider' => null, 'counterparty_type' => 'account',
                'type' => 'random', 'amount' => [500, 300000], 'metadata' => null,
            ],
            [
                'module' => 'transfers', 'transaction_type' => 'reversal',
                'business_service' => 'Failed Transfer Reversal', 'channel' => ['admin'],
                'service_provider' => 'NIBSS', 'counterparty_type' => 'external_bank',
                'type' => 'credit', 'amount' => [500, 100000], 'metadata' => null,
            ],
            [
                'module' => 'bills', 'transaction_type' => 'cable_tv',
                'business_service' => ['DSTV Compact', 'DSTV Compact Plus', 'GOtv Max', 'StarTimes Basic'],
                'channel' => ['mobile', 'internet_banking', 'ussd'], 'service_provider' => 'Baxi',
                'counterparty_type' => 'provider', 'business_service_name' => 'DSTV / MultiChoice',
                'type' => 'debit', 'amount' => [2000, 25000], 'metadata' => null,
            ],
            [
                'module' => 'bills', 'transaction_type' => 'electricity',
                'business_service' => ['Ikeja Electric Prepaid', 'Eko Electric Prepaid', 'Abuja Electric Prepaid'],
                'channel' => ['mobile', 'internet_banking'], 'service_provider' => 'Interswitch',
                'counterparty_type' => 'provider', 'business_service_name' => 'Electricity Distribution Company',
                'type' => 'debit', 'amount' => [1000, 50000],
                'metadata' => fn ($faker) => ['token' => $faker->numerify('#############'), 'meter_number' => $faker->numerify('###########')],
            ],
            [
                'module' => 'bills', 'transaction_type' => 'airtime',
                'business_service' => ['MTN Airtime', 'Airtel Airtime', 'Glo Airtime', '9mobile Airtime'],
                'channel' => ['mobile', 'ussd'], 'service_provider' => 'VTPass',
                'counterparty_type' => 'provider',
                'type' => 'debit', 'amount' => [100, 10000], 'metadata' => null,
            ],
            [
                'module' => 'bills', 'transaction_type' => 'data',
                'business_service' => ['MTN Data Bundle', 'Airtel Data Bundle', 'Glo Data Bundle', '9mobile Data Bundle'],
                'channel' => ['mobile'], 'service_provider' => 'VTPass',
                'counterparty_type' => 'provider',
                'type' => 'debit', 'amount' => [500, 15000], 'metadata' => null,
            ],
            [
                'module' => 'bills', 'transaction_type' => 'betting',
                'business_service' => ['Bet9ja Wallet Funding', 'SportyBet Wallet Funding', 'BetKing Wallet Funding'],
                'channel' => ['mobile', 'internet_banking'], 'service_provider' => 'Quickteller',
                'counterparty_type' => 'provider',
                'type' => 'debit', 'amount' => [500, 100000], 'metadata' => null,
            ],
            [
                'module' => 'card', 'transaction_type' => 'pos_purchase',
                'business_service' => ['Shoprite POS Purchase', 'Market Square POS Purchase', 'Filling Station POS Purchase'],
                'channel' => ['pos'], 'service_provider' => 'Interswitch',
                'counterparty_type' => 'merchant',
                'type' => 'debit', 'amount' => [1000, 200000], 'metadata' => null,
            ],
            [
                'module' => 'card', 'transaction_type' => 'atm_withdrawal',
                'business_service' => 'ATM Cash Withdrawal', 'channel' => ['atm'],
                'service_provider' => 'NIBSS', 'counterparty_type' => 'merchant',
                'type' => 'debit', 'amount' => [1000, 100000], 'metadata' => null,
            ],
            [
                'module' => 'card', 'transaction_type' => 'card_payment',
                'business_service' => ['Jumia Online Purchase', 'Konga Online Purchase', 'Netflix Subscription', 'Amazon Purchase'],
                'channel' => ['web'], 'service_provider' => 'Interswitch',
                'counterparty_type' => 'merchant',
                'type' => 'debit', 'amount' => [1000, 300000], 'metadata' => null,
            ],
            [
                'module' => 'card', 'transaction_type' => 'card_refund',
                'business_service' => ['Jumia Refund', 'Konga Refund'], 'channel' => ['web'],
                'service_provider' => 'Interswitch', 'counterparty_type' => 'merchant',
                'type' => 'credit', 'amount' => [1000, 150000], 'metadata' => null,
            ],
            [
                'module' => 'loans', 'transaction_type' => 'loan_disbursement',
                'business_service' => 'Personal Loan Disbursement', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Loans Portfolio',
                'type' => 'credit', 'amount' => [50000, 2000000], 'metadata' => null,
            ],
            [
                'module' => 'loans', 'transaction_type' => 'loan_repayment',
                'business_service' => 'Personal Loan Repayment', 'channel' => ['mobile', 'internet_banking'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Loans Portfolio',
                'type' => 'debit', 'amount' => [5000, 100000], 'metadata' => null,
            ],
            [
                'module' => 'loans', 'transaction_type' => 'loan_penalty',
                'business_service' => 'Late Repayment Penalty', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Loans Portfolio',
                'type' => 'debit', 'amount' => [500, 10000], 'metadata' => null,
            ],
            [
                'module' => 'investments', 'transaction_type' => 'investment_placement',
                'business_service' => ['Fixed Deposit - 30 Days', 'Fixed Deposit - 90 Days', 'Fixed Deposit - 180 Days'],
                'channel' => ['internet_banking'], 'service_provider' => null,
                'counterparty_type' => 'general_ledger', 'gl_name' => 'Fixed Deposit Portfolio',
                'type' => 'debit', 'amount' => [50000, 1000000], 'metadata' => null,
            ],
            [
                'module' => 'investments', 'transaction_type' => 'investment_maturity',
                'business_service' => 'Fixed Deposit Maturity', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Fixed Deposit Portfolio',
                'type' => 'credit', 'amount' => [50000, 1050000], 'metadata' => null,
            ],
            [
                'module' => 'interest', 'transaction_type' => 'interest_credit',
                'business_service' => 'Savings Interest', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Interest Expense',
                'type' => 'credit', 'amount' => [50, 20000], 'metadata' => null,
            ],
            [
                'module' => 'charges', 'transaction_type' => 'sms_alert_charge',
                'business_service' => 'SMS Alert Fee', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Fee Income',
                'type' => 'debit', 'amount' => [10, 100], 'metadata' => null,
            ],
            [
                'module' => 'charges', 'transaction_type' => 'stamp_duty',
                'business_service' => 'Stamp Duty (CBN)', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Stamp Duty Payable',
                'type' => 'debit', 'amount' => [1.5, 1.5], 'metadata' => null,
            ],
            [
                'module' => 'charges', 'transaction_type' => 'maintenance_fee',
                'business_service' => 'Account Maintenance Fee', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'general_ledger', 'gl_name' => 'Fee Income',
                'type' => 'debit', 'amount' => [50, 500], 'metadata' => null,
            ],
            [
                'module' => 'deposits', 'transaction_type' => 'salary_credit',
                'business_service' => 'Salary Payment', 'channel' => ['api'],
                'service_provider' => null, 'counterparty_type' => 'external_bank',
                'type' => 'credit', 'amount' => [80000, 1000000], 'metadata' => null,
            ],
            [
                'module' => 'deposits', 'transaction_type' => 'cash_deposit',
                'business_service' => 'Over-the-Counter Deposit', 'channel' => ['admin'],
                'service_provider' => null, 'counterparty_type' => 'external', 'gl_name' => 'Cash Deposit - Branch Teller',
                'type' => 'credit', 'amount' => [1000, 500000], 'metadata' => null,
            ],
        ];
    }
}
