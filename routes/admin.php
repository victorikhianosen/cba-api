<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AccountLienController;
use App\Http\Controllers\Admin\AccountOfficerController;
use App\Http\Controllers\Admin\AccountProductController;
use App\Http\Controllers\Admin\AccountTierController;
use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BvnController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\FinancialTransactionController;
use App\Http\Controllers\Admin\FundGlController;
use App\Http\Controllers\Admin\GeneralLedgerController;
use App\Http\Controllers\Admin\InvestmentController;
use App\Http\Controllers\Admin\AccountStatementTemplateController;
use App\Http\Controllers\Admin\InvestmentLetterTemplateController;
use App\Http\Controllers\Admin\InvestmentProductController;
use App\Http\Controllers\Admin\JournalEntryController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PostingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\CashierSessionController;
use App\Http\Controllers\Admin\StatusController;
use App\Http\Controllers\Admin\TellerController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserJourneyController;
use App\Http\Controllers\Admin\VaultController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:login');
    Route::post('2fa/verify', 'verifyTwoFactor')->middleware('throttle:login');
});

Route::middleware(['user'])->group(function () {
    Route::prefix('banks')->controller(BankController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_banks');
        Route::post('/', 'store')->middleware('permission:create_banks');
        Route::get('{id}', 'show')->middleware('permission:view_banks');
        Route::put('{id}', 'update')->middleware('permission:update_banks');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_banks');
        Route::post('{id}/logo', 'uploadLogo')->middleware('permission:update_banks');
    });

    Route::prefix('investment-letter-templates')->controller(InvestmentLetterTemplateController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_investment_letter_templates');
        Route::post('/', 'store')->middleware('permission:create_investment_letter_templates');
        Route::get('{id}', 'show')->middleware('permission:view_investment_letter_templates');
        Route::put('{id}', 'update')->middleware('permission:update_investment_letter_templates');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_investment_letter_templates');
        Route::post('{id}/set-default', 'setDefault')->middleware('permission:update_investment_letter_templates');
    });

    Route::prefix('account-statement-templates')->controller(AccountStatementTemplateController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_account_statement_templates');
        Route::post('/', 'store')->middleware('permission:create_account_statement_templates');
        Route::get('{id}', 'show')->middleware('permission:view_account_statement_templates');
        Route::put('{id}', 'update')->middleware('permission:update_account_statement_templates');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_account_statement_templates');
        Route::post('{id}/set-default', 'setDefault')->middleware('permission:update_account_statement_templates');
    });

    Route::prefix('branches')->controller(BranchController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_branches');
        Route::post('/', 'store')->middleware('permission:create_branches');
        Route::get('{id}', 'show')->middleware('permission:view_branches');
        Route::put('{id}', 'update')->middleware('permission:update_branches');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_branches');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_branches');
    });

    Route::prefix('users')->controller(UserController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_users');
        Route::post('/', 'store')->middleware('permission:create_users');
        Route::get('{id}', 'show')->middleware('permission:view_users');
        Route::put('{id}', 'update')->middleware('permission:update_users');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_users');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_users');
        Route::post('{id}/profile-picture', 'uploadProfilePicture')->middleware('permission:update_users');
        Route::post('{id}/reset-password', 'resetPassword')->middleware('permission:update_users');
        Route::post('{id}/roles', 'assignRoles')->middleware('permission:update_users');
        Route::delete('{id}/roles', 'removeRoles')->middleware('permission:update_users');
    });

    Route::prefix('roles')->controller(RoleController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_roles');
        Route::post('/', 'store')->middleware('permission:create_roles');
        Route::get('{id}', 'show')->middleware('permission:view_roles');
        Route::put('{id}', 'update')->middleware('permission:update_roles');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_roles');
        Route::put('{id}/assign', 'syncPermissions')->middleware('permission:update_roles');
    });

    Route::prefix('permissions')->controller(PermissionController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_permissions');
        Route::post('/', 'store')->middleware('permission:create_permissions');
        Route::get('{id}', 'show')->middleware('permission:view_permissions');
        Route::put('{id}', 'update')->middleware('permission:update_permissions');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_permissions');
    });

    // No POST/store route — currencies are a fixed, curated list seeded by
    // CurrencySeeder. Only rate/status/etc. updates on existing rows are allowed.
    Route::prefix('currencies')->controller(CurrencyController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_currencies');
        Route::get('{id}', 'show')->middleware('permission:view_currencies');
        Route::put('{id}', 'update')->middleware('permission:update_currencies');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_currencies');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_currencies');
    });

    Route::prefix('account-officers')->controller(AccountOfficerController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_account_officers');
        Route::post('/', 'store')->middleware('permission:create_account_officers');
        Route::get('{id}', 'show')->middleware('permission:view_account_officers');
        Route::put('{id}', 'update')->middleware('permission:update_account_officers');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_account_officers');
    });

    Route::prefix('account-products')->controller(AccountProductController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_account_products');
        Route::post('/', 'store')->middleware('permission:create_account_products');
        Route::get('{id}', 'show')->middleware('permission:view_account_products');
        Route::put('{id}', 'update')->middleware('permission:update_account_products');
        // Route::delete('{id}', 'destroy')->middleware('permission:delete_account_products');
        Route::post('{id}/approve', 'approve')->middleware('permission:approve_account_products');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_account_products');
    });

    Route::prefix('account-tiers')->controller(AccountTierController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_account_tiers');
        Route::post('/', 'store')->middleware('permission:create_account_tiers');
        Route::get('{id}', 'show')->middleware('permission:view_account_tiers');
        Route::put('{id}', 'update')->middleware('permission:update_account_tiers');
    });

    Route::prefix('loan-products')->controller(LoanProductController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_loan_products');
        Route::post('/', 'store')->middleware('permission:create_loan_products');
        Route::get('{id}', 'show')->middleware('permission:view_loan_products');
        Route::put('{id}', 'update')->middleware('permission:update_loan_products');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_loan_products');
        Route::post('{id}/approve', 'approve')->middleware('permission:approve_loan_products');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_loan_products');
    });

    Route::prefix('investment-products')->controller(InvestmentProductController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_investment_products');
        Route::post('/', 'store')->middleware('permission:create_investment_products');
        Route::get('{id}', 'show')->middleware('permission:view_investment_products');
        Route::put('{id}', 'update')->middleware('permission:update_investment_products');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_investment_products');
        Route::post('{id}/approve', 'approve')->middleware('permission:approve_investment_products');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_investment_products');
        Route::post('{id}/icon', 'uploadIcon')->middleware('permission:update_investment_products');
    });

    Route::prefix('vaults')->controller(VaultController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_vaults');
        Route::post('/', 'store')->middleware('permission:create_vaults');
        Route::get('{id}', 'show')->middleware('permission:view_vaults');
        Route::put('{id}', 'update')->middleware('permission:update_vaults');
        Route::post('{id}/approve', 'approve')->middleware(['permission:approve_vaults', 'totp']);
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_vaults');
    });

    Route::prefix('tellers')->controller(TellerController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_tellers');
        Route::post('/', 'store')->middleware('permission:create_tellers');
        Route::get('{id}', 'show')->middleware('permission:view_tellers');
        Route::put('{id}', 'update')->middleware('permission:update_tellers');
        Route::post('{id}/approve', 'approve')->middleware(['permission:approve_tellers', 'totp']);
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_tellers');
        Route::post('{id}/assign', 'assign')->middleware('permission:update_tellers');
        Route::post('{id}/unassign', 'unassign')->middleware('permission:update_tellers');
    });

    Route::prefix('cashier-sessions')->controller(CashierSessionController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_tellers');
        Route::get('mine', 'mine')->middleware('permission:operate_teller');
        Route::get('withdrawals/pending', 'pendingWithdrawals')->middleware('permission:approve_teller_withdrawal');
        Route::post('withdrawals/{transactionId}/approve', 'approveWithdrawal')->middleware(['permission:approve_teller_withdrawal', 'totp']);
        Route::post('withdrawals/{transactionId}/reject', 'rejectWithdrawal')->middleware('permission:approve_teller_withdrawal');
        Route::get('{id}', 'show')->middleware('permission:view_tellers');
        Route::post('open', 'open')->middleware('permission:operate_teller');
        Route::post('{id}/buy', 'buy')->middleware('permission:operate_teller');
        Route::post('{id}/sell', 'sell')->middleware('permission:operate_teller');
        Route::post('{id}/deposit', 'deposit')->middleware('permission:operate_teller');
        Route::post('{id}/withdraw', 'withdraw')->middleware('permission:operate_teller');
        Route::post('{id}/close', 'close')->middleware('permission:operate_teller');
        Route::post('{id}/reconcile', 'reconcile')->middleware('permission:operate_teller');
        Route::post('{id}/approve-variance', 'approveVariance')->middleware(['permission:approve_cashier_variance', 'totp']);
        Route::post('{id}/reject-variance', 'rejectVariance')->middleware('permission:approve_cashier_variance');
    });

    Route::get('bvn/lookup', [BvnController::class, 'lookup'])->middleware('permission:create_customers');

    Route::prefix('customers')->controller(CustomerController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_customers');
        Route::post('/', 'store')->middleware('permission:create_customers');
        Route::post('bulk', 'bulkUpdate')->middleware('permission:update_customers');
        Route::get('bulk-import/template', 'bulkImportTemplate')->middleware('permission:create_customers');
        Route::post('bulk-import', 'bulkImport')->middleware('permission:create_customers');
        Route::get('lookup', 'lookup')->middleware('permission:view_customers');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_customers');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_customers');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_customers');
        Route::get('{id}', 'show')->middleware('permission:view_customers');
        Route::put('{id}', 'update')->middleware('permission:update_customers');
        Route::post('{id}/approve', 'approve')->middleware('permission:update_customers');
        Route::post('{id}/reject', 'reject')->middleware('permission:update_customers');
        Route::post('{id}/close', 'close')->middleware('permission:update_customers');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_customers');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_customers');
        Route::post('{id}/documents', 'uploadDocuments')->middleware('permission:update_customers');
        Route::put('{customerId}/documents/{documentId}', 'updateDocument')->middleware('permission:update_customers');
        Route::post('{id}/accounts', 'storeAccount')->middleware('permission:create_accounts');
        Route::post('{id}/accounts/status', 'updateAccountsStatus')->middleware('permission:update_customers');
        Route::post('{id}/reset-password', 'resetPassword')->middleware('permission:update_customers');
        Route::post('{id}/reset-pin', 'resetPin')->middleware('permission:update_customers');
        Route::post('{id}/toggle-internet-banking', 'toggleInternetBanking')->middleware('permission:update_customers');
        Route::post('{id}/toggle-sms', 'toggleSms')->middleware('permission:update_customers');
        Route::post('{id}/toggle-email', 'toggleEmail')->middleware('permission:update_customers');
    });

    Route::get('statuses', [StatusController::class, 'index'])->middleware('permission:view_customers');

    // No `permission:` gate — every authenticated user (of any role) sees their own
    // notifications; the outer 'user' group middleware is the only guard needed.
    Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('read-all', 'markAllAsRead');
        Route::post('{id}/read', 'markAsRead');
    });

    Route::prefix('accounts')->controller(AccountController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_accounts');
        Route::get('lookup', 'lookup')->middleware('permission:view_accounts');
        Route::get('{id}', 'show')->middleware('permission:view_accounts');
        Route::get('{id}/transactions', 'transactions')->middleware('permission:view_accounts');
        Route::get('{id}/statement', 'statement')->middleware('permission:view_accounts');
        Route::post('{id}/statement/email', 'emailStatement')->middleware('permission:view_accounts');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_accounts');
        Route::post('{id}/lien', 'placeLien')->middleware('permission:create_liens');
        Route::get('{id}/liens', 'liens')->middleware('permission:view_accounts');
        Route::post('{id}/toggle-pnd', 'togglePnd')->middleware('permission:update_accounts');
        Route::post('{id}/toggle-pnc', 'togglePnc')->middleware('permission:update_accounts');
    });

    Route::prefix('account-liens')->controller(AccountLienController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_liens');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_liens');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_liens');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_liens');
        Route::get('{id}', 'show')->middleware('permission:view_liens');
        Route::post('{id}/approve', 'approve')->middleware(['permission:approve_liens', 'totp']);
        Route::post('{id}/reject', 'reject')->middleware('permission:approve_liens');
        Route::post('{id}/release', 'release')->middleware(['permission:approve_liens', 'totp']);
    });

    Route::prefix('investments')->controller(InvestmentController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_investments');
        Route::post('/', 'store')->middleware('permission:create_investments');
        Route::post('preview-schedule', 'previewSchedule')->middleware('permission:create_investments');
        Route::get('{id}', 'show')->middleware('permission:view_investments');
        Route::get('{id}/transactions', 'transactions')->middleware('permission:view_investments');
        Route::get('{id}/schedule', 'schedule')->middleware('permission:view_investments');
        Route::get('{id}/schedule/previous', 'previousSchedule')->middleware('permission:view_investments');
        Route::get('{id}/letter', 'letterPdf')->middleware('permission:view_investments');
        Route::post('{id}/letter/email', 'emailLetter')->middleware('permission:view_investments');
        Route::get('{id}/approval-preview', 'previewApproval')->middleware('permission:approve_investments');
        Route::post('{id}/approve', 'approve')->middleware(['permission:approve_investments', 'totp']);
        Route::post('{id}/reject', 'reject')->middleware('permission:approve_investments');
        Route::post('{id}/topup/preview', 'previewTopUp')->middleware('permission:create_investments');
        Route::post('{id}/topup', 'requestTopUp')->middleware('permission:create_investments');
        Route::get('{id}/topup/pending-preview', 'previewPendingTopUp')->middleware('permission:approve_investments');
        Route::post('{id}/topup/approve', 'approveTopUp')->middleware(['permission:approve_investments', 'totp']);
        Route::post('{id}/topup/reject', 'rejectTopUp')->middleware('permission:approve_investments');
        Route::post('{id}/terminate', 'terminate')->middleware('permission:approve_investments');
    });

    Route::prefix('general-ledgers')->controller(GeneralLedgerController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_general_ledgers');
        Route::post('/', 'store')->middleware('permission:create_general_ledgers');
        Route::get('available', 'available')->middleware('permission:view_general_ledgers');
        Route::get('stats', 'stats')->middleware('permission:view_general_ledgers');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_general_ledgers');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_general_ledgers');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_general_ledgers');
        Route::get('{id}', 'show')->middleware('permission:view_general_ledgers');
        Route::put('{id}', 'update')->middleware('permission:update_general_ledgers');
        Route::delete('{id}', 'destroy')->middleware('permission:delete_general_ledgers');
        Route::post('{id}/status', 'updateStatus')->middleware('permission:update_general_ledgers');
    });

    Route::prefix('journal-entries')->controller(JournalEntryController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_general_ledgers');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_general_ledgers');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_general_ledgers');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_general_ledgers');
        Route::get('{id}', 'show')->middleware('permission:view_general_ledgers');
    });

    Route::prefix('financial-transactions')->controller(FinancialTransactionController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_general_ledgers');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_general_ledgers');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_general_ledgers');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_general_ledgers');
        Route::get('{id}', 'show')->middleware('permission:view_general_ledgers');
    });

    Route::prefix('transactions')->controller(TransactionController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_general_ledgers');
        Route::get('types', 'types')->middleware('permission:view_general_ledgers');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_general_ledgers');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_general_ledgers');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_general_ledgers');
        Route::get('{id}', 'show')->middleware('permission:view_general_ledgers');
    });

    Route::prefix('postings')->controller(PostingController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_postings');
        Route::post('/', 'store')->middleware('permission:create_postings');
        Route::post('bulk-approve', 'bulkApprove')->middleware(['permission:approve_postings', 'totp']);
        Route::get('bulk-import/template', 'bulkImportTemplate')->middleware('permission:create_postings');
        Route::post('bulk-import', 'bulkImport')->middleware('permission:create_postings');
        Route::get('export/csv', 'exportCsvFile')->middleware('permission:view_postings');
        Route::get('export/excel', 'exportExcelFile')->middleware('permission:view_postings');
        Route::get('export/pdf', 'exportPdfFile')->middleware('permission:view_postings');
        Route::get('{id}', 'show')->middleware('permission:view_postings');
        Route::post('{id}/approve', 'approve')->middleware(['permission:approve_postings', 'totp']);
        Route::post('{id}/reject', 'reject')->middleware('permission:approve_postings');
    });

    Route::prefix('fund-gls')->controller(FundGlController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_fund_gls');
        Route::post('/', 'store')->middleware('permission:create_fund_gls');
        Route::get('{id}', 'show')->middleware('permission:view_fund_gls');
        Route::post('{id}/review', 'review')->middleware(['permission:review_fund_gls', 'totp']);
        Route::post('{id}/approve', 'approve')->middleware(['permission:approve_fund_gls', 'totp']);
        Route::post('{id}/reject', 'reject')->middleware('permission:review_fund_gls');
    });

    Route::prefix('audit-trails')->controller(AuditTrailController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_audit_trails');
        Route::get('{id}', 'show')->middleware('permission:view_audit_trails');
    });

    Route::prefix('communications')->controller(CommunicationController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_communications');
        Route::get('{id}', 'show')->middleware('permission:view_communications');
        Route::post('email', 'sendEmail')->middleware('permission:create_communications');
        Route::post('email/bulk', 'sendBulkEmail')->middleware('permission:create_communications');
        Route::post('sms', 'sendSms')->middleware('permission:create_communications');
        Route::post('sms/bulk', 'sendBulkSms')->middleware('permission:create_communications');
    });

    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::get('me', 'me');
        Route::post('logout', 'logout');
        Route::post('session-timeout', 'sessionTimeout');
    });

    Route::prefix('user-journeys')->controller(UserJourneyController::class)->group(function () {
        Route::get('/', 'index')->middleware('permission:view_user_journeys');
        Route::get('{sessionId}', 'show')->middleware('permission:view_user_journeys');
    });
});
