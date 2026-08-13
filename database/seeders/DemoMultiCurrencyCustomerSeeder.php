<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountProduct;
use App\Models\Customer;
use App\Models\GeneralLedger;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Customer\CustomerService;
use App\Services\Posting\PostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

// 5 demo customers, each with one funded account per currency (NGN, USD, GBP, EUR) —
// exercises the "pick which of this customer's own accounts to debit" repayment flow
// (currency-match + sufficient-funds validation) against real multi-currency data instead
// of the single NGN account CustomerAccountSeeder provides.
class DemoMultiCurrencyCustomerSeeder extends Seeder
{
    private const CUSTOMERS = [
        ['cif' => 'CIF-DEMO-0001', 'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada.okafor@example.com', 'phone' => '08021110001'],
        ['cif' => 'CIF-DEMO-0002', 'first_name' => 'Bola', 'last_name' => 'Adeyemi', 'email' => 'bola.adeyemi@example.com', 'phone' => '08021110002'],
        ['cif' => 'CIF-DEMO-0003', 'first_name' => 'Chidi', 'last_name' => 'Eze', 'email' => 'chidi.eze@example.com', 'phone' => '08021110003'],
        ['cif' => 'CIF-DEMO-0004', 'first_name' => 'Ngozi', 'last_name' => 'Bello', 'email' => 'ngozi.bello@example.com', 'phone' => '08021110004'],
        ['cif' => 'CIF-DEMO-0005', 'first_name' => 'Femi', 'last_name' => 'Balogun', 'email' => 'femi.balogun@example.com', 'phone' => '08021110005'],
    ];

    // Product name per currency — matches what AccountProductSeeder actually seeds.
    private const PRODUCT_NAME_BY_CURRENCY = [
        'NGN' => 'Basic Savings Account',
        'USD' => 'USD Domiciliary Account',
        'GBP' => 'GBP Savings Account',
        'EUR' => 'EUR Savings Account',
    ];

    private const FUNDING_AMOUNT_BY_CURRENCY = [
        'NGN' => '1500000',
        'USD' => '5000',
        'GBP' => '4000',
        'EUR' => '4500',
    ];

    public function run(): void
    {
        $this->call(AccountProductSeeder::class);

        $maker   = User::where('username', 'admin1')->firstOrFail();
        $checker = User::where('username', 'admin2')->firstOrFail();

        foreach (self::CUSTOMERS as $data) {
            Auth::guard('user')->setUser($maker);
            $customer = $this->seedCustomer($data);

            foreach (self::PRODUCT_NAME_BY_CURRENCY as $currencyCode => $productName) {
                Auth::guard('user')->setUser($maker);
                $account = $this->seedAccount($customer, $productName);
                $this->fundAccount($account, self::FUNDING_AMOUNT_BY_CURRENCY[$currencyCode], $maker, $checker);
            }
        }
    }

    private function seedCustomer(array $data): Customer
    {
        $existing = Customer::where('cif_number', $data['cif'])->first();
        if ($existing) {
            return $existing;
        }

        $customer = app(CustomerService::class)->create([
            'customer_type' => 'individual',
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'gender'        => 'male',
            'dob'           => '1990-01-01',
        ]);

        $customer->update(['cif_number' => $data['cif'], 'status' => 'active']);

        return $customer->refresh();
    }

    private function seedAccount(Customer $customer, string $productName): Account
    {
        $product = AccountProduct::where('name', $productName)->firstOrFail();

        $existing = Account::where('customer_id', $customer->id)
            ->where('account_product_id', $product->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return app(AccountService::class)->openAccountForCustomer($customer, [
            'account_product_id' => $product->id,
            'account_type'       => $product->product_type,
        ]);
    }

    // Mirrors CustomerAccountSeeder::fundAccount() — a real teller cash deposit through the
    // posting engine (maker + a different checker), topping up each currency's Cash in
    // Vault GL first if it doesn't hold enough yet.
    private function fundAccount(Account $account, string $amount, User $maker, User $checker): void
    {
        if ((float) $account->account_balance >= (float) $amount) {
            return;
        }

        $cashInVaultName = $account->currency_id === $this->ngnCurrencyId()
            ? 'cash_in_vault'
            : strtolower($account->currency->code) . '_cash_in_vault';

        $cashInVault = GeneralLedger::where('name', $cashInVaultName)->first()
            ?? GeneralLedger::where('type', 'cash')->where('currency_id', $account->currency_id)->first();

        if (! $cashInVault) {
            return;
        }

        if ((float) $cashInVault->balance < (float) $amount) {
            $cashInVault->update(['balance' => $amount]);
        }

        Auth::guard('user')->setUser($maker);

        $posting = app(PostingService::class)->create([
            'source_type'      => 'general_ledger',
            'source_id'        => $cashInVault->id,
            'destination_type' => 'account',
            'destination_id'   => $account->id,
            'amount'           => $amount,
            'module'           => 'postings',
            'transaction_type' => 'cash_deposit',
            'channel'          => 'branch',
            'remarks'          => "Seed funding for account '{$account->account_number}'.",
        ]);

        Auth::guard('user')->setUser($checker);

        app(PostingService::class)->approve($posting);
    }

    private function ngnCurrencyId(): int
    {
        static $id = null;

        return $id ??= \App\Models\Currency::where('code', 'NGN')->value('id');
    }
}
