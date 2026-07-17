<?php

namespace App\Providers;

use App\Models\AccountProduct;
use App\Models\InvestmentProduct;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        ]);

        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
    }
}
