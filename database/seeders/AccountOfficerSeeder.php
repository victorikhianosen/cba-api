<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountOfficer;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class AccountOfficerSeeder extends Seeder
{
    // first_name, last_name, branch_code, city, state
    private const DEFINITIONS = [
        ['Adaeze', 'Okafor', 'BR001', 'Victoria Island', 'Lagos'],
        ['Tunde', 'Bakare', 'BR001', 'Victoria Island', 'Lagos'],
        ['Chinwe', 'Eze', 'BR002', 'Ikeja', 'Lagos'],
        ['Segun', 'Adeyemi', 'BR002', 'Ikeja', 'Lagos'],
        ['Fatima', 'Abubakar', 'BR003', 'Garki', 'FCT'],
        ['Emeka', 'Nwosu', 'BR003', 'Garki', 'FCT'],
        ['Blessing', 'Etim', 'BR004', 'GRA', 'Rivers'],
        ['Ibrahim', 'Musa', 'BR004', 'GRA', 'Rivers'],
        ['Amaka', 'Chukwu', 'BR005', 'Nassarawa', 'Kano'],
        ['Yusuf', 'Garba', 'BR005', 'Nassarawa', 'Kano'],
    ];

    public function run(): void
    {
        $branchIds = Branch::whereIn('code', array_unique(array_column(self::DEFINITIONS, 2)))
            ->pluck('id', 'code');

        foreach (self::DEFINITIONS as $index => [$firstName, $lastName, $branchCode, $city, $state]) {
            $sequence = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            AccountOfficer::updateOrCreate(
                ['code' => "AO{$sequence}"],
                [
                    'branch_id' => $branchIds[$branchCode],
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => strtolower("{$firstName}.{$lastName}@reacquire.test"),
                    'phone'      => '0802000' . $sequence,
                    'gender'     => null,
                    'address'    => "{$lastName} Street",
                    'city'       => $city,
                    'state'      => $state,
                    'country'    => 'Nigeria',
                    'status'     => 'active',
                ],
            );
        }
    }
}
