<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    // Must run after RolePermissionSeeder — 'super_admin'/'admin' need to exist before syncRoles() below.
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['username' => 'john'],
            [
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'code'              => 'USR000001',
                'email'             => 'superadmin@example.com',
                'email_verified_at' => now(),
                'password'          => Hash::make('12345678'),
                'status'            => 'active',
            ],
        );
        $superAdmin->syncRoles(['super_admin']);

        $admin1 = User::updateOrCreate(
            ['username' => 'admin1'],
            [
                'first_name'        => 'Admin',
                'last_name'         => 'One',
                'code'              => 'USR000002',
                'email'             => 'admin1@example.com',
                'email_verified_at' => now(),
                'password'          => Hash::make('1234567890'),
                'status'            => 'active',
            ],
        );
        $admin1->syncRoles(['admin']);

        $admin2 = User::updateOrCreate(
            ['username' => 'admin2'],
            [
                'first_name'        => 'Admin',
                'last_name'         => 'Two',
                'code'              => 'USR000003',
                'email'             => 'admin2@example.com',
                'email_verified_at' => now(),
                'password'          => Hash::make('1234567890'),
                'status'            => 'active',
            ],
        );
        $admin2->syncRoles(['admin']);
    }
}
