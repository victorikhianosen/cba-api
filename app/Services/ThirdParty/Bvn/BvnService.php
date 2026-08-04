<?php

declare(strict_types=1);

namespace App\Services\ThirdParty\Bvn;

// Stubbed BVN lookup — returns randomized dummy personal data for any 11-digit BVN
// until a real provider (e.g. NIBSS) is wired in behind this same interface.
class BvnService
{
    public function lookup(string $bvn): array
    {
        $titles          = ['Mr', 'Mrs', 'Miss', 'Dr', 'Engr'];
        $genders         = ['male', 'female'];
        $maritalStatuses = ['single', 'married', 'divorced', 'widowed'];
        $firstNames      = ['Chinedu', 'Adaeze', 'Tunde', 'Ngozi', 'Bola', 'Ifeoma', 'Segun', 'Amaka'];
        $lastNames       = ['Okafor', 'Adeyemi', 'Balogun', 'Eze', 'Okonkwo', 'Abubakar', 'Nwosu', 'Uche'];
        $states          = ['Lagos', 'Abuja', 'Rivers', 'Oyo', 'Kano', 'Enugu'];

        $gender = $genders[array_rand($genders)];

        return [
            'bvn'                => $bvn,
            'title'              => $titles[array_rand($titles)],
            'first_name'         => $firstNames[array_rand($firstNames)],
            'middle_name'        => $firstNames[array_rand($firstNames)],
            'last_name'          => $lastNames[array_rand($lastNames)],
            'gender'             => $gender,
            'dob'                => now()->subYears(random_int(18, 60))->subDays(random_int(0, 365))->format('Y-m-d'),
            'phone'              => '070' . random_int(10000000, 99999999),
            'email'              => strtolower($firstNames[array_rand($firstNames)]) . random_int(100, 999) . '@example.com',
            'marital_status'     => $maritalStatuses[array_rand($maritalStatuses)],
            'mother_maiden_name' => $lastNames[array_rand($lastNames)],
            'occupation'         => 'Trader',
            'address'            => [
                'address_line_1' => random_int(1, 200) . ' Example Street',
                'city'           => $states[array_rand($states)],
                'state'          => $states[array_rand($states)],
                'country'        => 'Nigeria',
            ],
        ];
    }
}
