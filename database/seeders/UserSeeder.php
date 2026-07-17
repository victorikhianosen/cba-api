<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'john'],
            [
                'first_name'        => 'John',
                'last_name'         => 'Doe',
                'code'              => 'USR000001',
                'email'             => 'john@example.com',
                'email_verified_at' => now(),
                'password'          => Hash::make('securePassword123'),
                'status'            => 'active',
            ],
        );
    }
}
