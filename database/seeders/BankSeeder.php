<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bank;
use App\Services\Bank\BankService;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    // A handful of well-known Nigerian banks (NIBSS institution codes) for interbank
    // posting/counterparty test data — not an exhaustive directory.
    private const BANKS = [
        ['bank_name' => 'Guaranty Trust Bank', 'bank_code' => '000013', 'short_code' => '058'],
        ['bank_name' => 'Access Bank', 'bank_code' => '000014', 'short_code' => '044'],
        ['bank_name' => 'Zenith Bank', 'bank_code' => '000015', 'short_code' => '057'],
        ['bank_name' => 'First Bank of Nigeria', 'bank_code' => '000016', 'short_code' => '011'],
        ['bank_name' => 'United Bank for Africa', 'bank_code' => '000004', 'short_code' => '033'],
    ];

    public function run(): void
    {
        $service = app(BankService::class);

        foreach (self::BANKS as $bank) {
            if (Bank::where('bank_code', $bank['bank_code'])->exists()) {
                continue;
            }

            $service->create($bank);
        }
    }
}
