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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('components.header.notification-dropdown', function ($view) {
            $view->with('schoolNotifications', auth()->user()
                ? app(\App\Services\SchoolNotificationFeed::class)->forUser(auth()->user())
                : collect());
        });
    }
}
