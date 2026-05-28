<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('auth.strict', function (Request $request) {
            return Limit::perMinute(5)->by('ip:'.$request->ip());
        });

        RateLimiter::for('cart.mutations', function (Request $request) {
            $tokenable = $request->user();
            $key = $tokenable ? (get_class($tokenable).':'.$tokenable->getAuthIdentifier()) : ('ip:'.$request->ip());

            return Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('checkout.strict', function (Request $request) {
            $tokenable = $request->user();
            $key = $tokenable ? (get_class($tokenable).':'.$tokenable->getAuthIdentifier()) : ('ip:'.$request->ip());

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('mock.payment.strict', function (Request $request) {
            return Limit::perMinute(3)->by('ip:'.$request->ip());
        });
    }
}
