<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Observers\CatalogCacheObserver;
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
        $observer = CatalogCacheObserver::class;
        Product::observe($observer);
        Category::observe($observer);
        Brand::observe($observer);
        ProductImage::observe($observer);
        ProductVariant::observe($observer);
        StockLevel::observe($observer);

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

        RateLimiter::for('account.deletion.strict', function (Request $request) {
            $tokenable = $request->user();
            $key = $tokenable ? ('customer:'.$tokenable->getAuthIdentifier()) : ('ip:'.$request->ip());

            return Limit::perMinute(3)->by($key);
        });
    }
}
