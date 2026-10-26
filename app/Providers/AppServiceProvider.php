<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\BillingWhatsAppGateway::class, \App\Services\DeferredBillingWhatsAppGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([\App\Models\FinanceIncome::class, \App\Models\Expense::class, \App\Models\Transaction::class,
            \App\Models\PaymentSubmission::class, \App\Models\CommitteeReceipt::class,
            \App\Models\FinanceAccountTransfer::class, \App\Models\FinanceWalletMovement::class] as $model) {
            $model::observe(\App\Observers\FinancePeriodObserver::class);
        }
        \Illuminate\Support\Facades\View::composer('components.header.notification-dropdown', function ($view) {
            $view->with('schoolNotifications', auth()->user()
                ? app(\App\Services\SchoolNotificationFeed::class)->forUser(auth()->user())
                : collect());
        });
    }
}
