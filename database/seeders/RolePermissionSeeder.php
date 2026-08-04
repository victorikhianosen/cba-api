<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    // Permissions covering CRUD/approve actions across every route currently built, all granted to the 'admin' role.
    private const PERMISSIONS = [
        'view_users', 'create_users', 'update_users', 'delete_users',
        'view_roles', 'create_roles', 'update_roles', 'delete_roles',
        'view_permissions', 'create_permissions', 'update_permissions', 'delete_permissions',
        'view_branches', 'create_branches', 'update_branches', 'delete_branches',
        'view_banks', 'create_banks', 'update_banks', 'delete_banks',
        'view_currencies', 'update_currencies', 'delete_currencies',
        'view_customers', 'create_customers', 'update_customers', 'delete_customers',
        'view_accounts', 'create_accounts', 'update_accounts',
        'view_account_officers', 'create_account_officers', 'update_account_officers', 'delete_account_officers',
        'view_account_tiers', 'create_account_tiers', 'update_account_tiers',
        'view_general_ledgers', 'create_general_ledgers', 'update_general_ledgers', 'delete_general_ledgers',
        'view_postings', 'create_postings', 'approve_postings',
        'view_account_products', 'create_account_products', 'update_account_products', 'delete_account_products', 'approve_account_products',
        'view_loan_products', 'create_loan_products', 'update_loan_products', 'delete_loan_products', 'approve_loan_products',
        'view_investment_products', 'create_investment_products', 'update_investment_products', 'delete_investment_products', 'approve_investment_products',
        'view_investments', 'create_investments', 'approve_investments',
        'view_audit_trails',
        'view_user_journeys',
        'view_communications', 'create_communications',
        'view_vaults', 'create_vaults', 'update_vaults', 'delete_vaults', 'approve_vaults',
        'view_tellers', 'create_tellers', 'update_tellers', 'delete_tellers', 'approve_tellers',
        'operate_teller',              // cashier-facing: open/close/buy/sell/deposit/request-withdrawal — granted to the cashier role
        'approve_cashier_variance',    // second-admin sign-off on an out-of-tolerance teller reconciliation — NOT granted to cashier
        'approve_teller_withdrawal',   // second-admin sign-off on a customer cash withdrawal — NOT granted to cashier
    ];

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'user']),
        );

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'user']);
        $admin->givePermissionTo($permissions);

        // super_admin gets every permission that exists (synced, not just granted) plus an unconditional Gate::before bypass in AppServiceProvider.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'user']);
        $superAdmin->syncPermissions($permissions);

        // Cashiers only operate their own teller day-to-day — they don't manage the
        // vault/teller catalog and can't approve their own (or anyone else's) variance.
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'user']);
        $cashier->givePermissionTo(['operate_teller', 'view_tellers']);
    }
}
