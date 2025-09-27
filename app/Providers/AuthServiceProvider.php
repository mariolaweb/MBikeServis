<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Implicitly grant "master-admin" role all permission checks using can(), master admin može sve
        Gate::before(function ($user, $ability) {
            if ($user && $user->hasRole('master-admin')) {
                return true;
            }
            return null; // vrati null da ostali Gate checkovi nastave normalno
        });
    }
}
