<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Account;
use App\Models\AccountProduct;
use App\Models\GeneralLedger;
use App\Models\InvestmentProduct;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'account_product'    => AccountProduct::class,
            'loan_product'       => LoanProduct::class,
            'investment_product' => InvestmentProduct::class,
            'account'            => Account::class,
            'general_ledger'     => GeneralLedger::class,
        ]);

        Gate::before(fn (User $user) => $user->hasRole(['admin', 'super_admin']) ? true : null);

        RateLimiter::for('login', function ($request) {
            $key = Str::lower($request->input('email', $request->input('username', ''))) . '|' . $request->ip();

            return [
                Limit::perMinute(5)->by($key),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
