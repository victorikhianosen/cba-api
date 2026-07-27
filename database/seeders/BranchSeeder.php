<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    // name, code, email, phone, city, state
    private const DEFINITIONS = [
        ['Head Office', 'BR001', 'headoffice@reacquire.test', '08010000001', 'Victoria Island', 'Lagos'],
        ['Ikeja Branch', 'BR002', 'ikeja@reacquire.test', '08010000002', 'Ikeja', 'Lagos'],
        ['Abuja Branch', 'BR003', 'abuja@reacquire.test', '08010000003', 'Garki', 'FCT'],
        ['Port Harcourt Branch', 'BR004', 'portharcourt@reacquire.test', '08010000004', 'GRA', 'Rivers'],
        ['Kano Branch', 'BR005', 'kano@reacquire.test', '08010000005', 'Nassarawa', 'Kano'],
    ];

    public function run(): void
    {
        foreach (self::DEFINITIONS as [$name, $code, $email, $phone, $city, $state]) {
            Branch::updateOrCreate(
                ['code' => $code],
                [
                    'name'    => $name,
                    'email'   => $email,
                    'phone'   => $phone,
                    'address' => "{$name} Complex",
                    'city'    => $city,
                    'state'   => $state,
                    'country' => 'Nigeria',
                    'status'  => 'active',
                ],
            );
        }
    }
}
