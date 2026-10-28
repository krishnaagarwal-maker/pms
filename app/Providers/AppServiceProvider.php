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
    // public function boot(): void
    // {
    //      if (env('APP_ENV') !== 'local' || isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    //     URL::forceScheme('https');
    // }
}

