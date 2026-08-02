<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(GeneralLedgerSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(AccountOfficerSeeder::class);
        $this->call(AccountTierSeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(BankSeeder::class);
        $this->call(AccountProductSeeder::class);
        $this->call(CustomerAccountSeeder::class);
    }
}
