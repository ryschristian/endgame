<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('manage-accounts', fn (User $user) => $user->is_active && $user->is_system_admin);
        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('account-notifications', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('account-tokens', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('account-management', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()));
    }
}
