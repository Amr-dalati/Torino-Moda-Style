<?php

namespace App\Support\Ops;

use App\Models\DeliveryArea;
use App\Models\DeliveryRegion;
use App\Models\Product;
use App\Support\Production\CheckStatus;
use App\Support\Production\ProductionCheck;
use App\Support\Production\ThawaniReadinessChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class SmokeTestRunner
{
    public function __construct(
        protected SchedulerHeartbeat $schedulerHeartbeat,
        protected ThawaniReadinessChecker $thawaniChecker,
    ) {}

    /**
     * @return list<ProductionCheck>
     */
    public function run(bool $withAuth = false): array
    {
        $checks = [
            $this->checkLiveness(),
            $this->checkReadiness(),
            $this->checkDatabase(),
            $this->checkCache(),
            $this->checkStorage(),
            $this->checkSchedulerHeartbeat(),
            $this->checkCatalog(),
            $this->checkDeliveryRegions(),
            $this->checkPaymentProvider(),
            ...$this->thawaniConfigurationChecks(),
            ...$this->checkLegalRoutes(),
            $this->checkWebhookRoute(),
            $this->checkHttpsCallbacks(),
            $this->checkRequiredApiRoutes(),
        ];

        if ($withAuth) {
            $checks[] = $this->checkAuthenticatedCatalog();
        }

        return $checks;
    }

    public function hasFailures(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check->status === CheckStatus::Fail) {
                return true;
            }
        }

        return false;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('app.url', ''), '/');
    }

    protected function checkLiveness(): ProductionCheck
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl()}/api/health");

            if ($response->successful() && ($response->json('status') === 'ok' || $response->json('data.status') === 'ok')) {
                return new ProductionCheck('liveness', CheckStatus::Pass, 'GET /api/health returned OK.');
            }

            return new ProductionCheck('liveness', CheckStatus::Fail, 'GET /api/health did not return expected status.');
        } catch (\Throwable) {
            return new ProductionCheck('liveness', CheckStatus::Fail, 'GET /api/health is unreachable.');
        }
    }

    protected function checkReadiness(): ProductionCheck
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl()}/api/readiness");
            $status = $response->json('data.status') ?? $response->json('status');

            if ($response->successful() && in_array($status, ['ready', 'degraded'], true)) {
                return new ProductionCheck('readiness', CheckStatus::Pass, "GET /api/readiness returned '{$status}'.");
            }

            if ($response->status() === 503) {
                return new ProductionCheck('readiness', CheckStatus::Fail, 'GET /api/readiness returned not_ready (503).');
            }

            return new ProductionCheck('readiness', CheckStatus::Warning, 'GET /api/readiness returned an unexpected response.');
        } catch (\Throwable) {
            return new ProductionCheck('readiness', CheckStatus::Fail, 'GET /api/readiness is unreachable.');
        }
    }

    protected function checkDatabase(): ProductionCheck
    {
        try {
            DB::connection()->getPdo();

            return new ProductionCheck('database', CheckStatus::Pass, 'Database connection is available.');
        } catch (\Throwable) {
            return new ProductionCheck('database', CheckStatus::Fail, 'Database connection failed.');
        }
    }

    protected function checkCache(): ProductionCheck
    {
        try {
            Cache::store()->put('smoke_test_probe', 'ok', 10);
            $value = Cache::store()->get('smoke_test_probe');
            Cache::store()->forget('smoke_test_probe');

            if ($value === 'ok') {
                return new ProductionCheck('cache', CheckStatus::Pass, 'Cache store is operational.');
            }

            return new ProductionCheck('cache', CheckStatus::Fail, 'Cache read/write probe failed.');
        } catch (\Throwable) {
            return new ProductionCheck('cache', CheckStatus::Fail, 'Cache store is not operational.');
        }
    }

    protected function checkStorage(): ProductionCheck
    {
        try {
            $path = 'smoke-tests/'.uniqid('probe_', true).'.txt';
            Storage::disk('local')->put($path, 'ok');
            $contents = Storage::disk('local')->get($path);
            Storage::disk('local')->delete($path);

            if ($contents === 'ok') {
                return new ProductionCheck('storage', CheckStatus::Pass, 'Local storage is readable and writable.');
            }

            return new ProductionCheck('storage', CheckStatus::Fail, 'Storage probe failed.');
        } catch (\Throwable) {
            return new ProductionCheck('storage', CheckStatus::Fail, 'Storage is not operational.');
        }
    }

    protected function checkSchedulerHeartbeat(): ProductionCheck
    {
        if (app()->environment(['local', 'testing'])) {
            return new ProductionCheck('scheduler_heartbeat', CheckStatus::Warning, 'Scheduler heartbeat not required in local/testing.');
        }

        if ($this->schedulerHeartbeat->isFresh(10)) {
            return new ProductionCheck('scheduler_heartbeat', CheckStatus::Pass, 'Scheduler heartbeat is fresh.');
        }

        return new ProductionCheck('scheduler_heartbeat', CheckStatus::Warning, 'Scheduler heartbeat is stale or missing; verify cron is running.');
    }

    protected function checkCatalog(): ProductionCheck
    {
        try {
            $count = Product::query()->where('is_active', true)->count();

            if ($count > 0) {
                return new ProductionCheck('catalog', CheckStatus::Pass, "Active products available ({$count}).");
            }

            return new ProductionCheck('catalog', CheckStatus::Warning, 'No active products found; run staging seeder if UAT catalog is required.');
        } catch (\Throwable) {
            return new ProductionCheck('catalog', CheckStatus::Fail, 'Could not query product catalog.');
        }
    }

    protected function checkDeliveryRegions(): ProductionCheck
    {
        try {
            $count = DeliveryRegion::query()->where('is_active', true)->count();

            if ($count > 0) {
                return new ProductionCheck('delivery_regions', CheckStatus::Pass, "Active delivery regions available ({$count}).");
            }

            return new ProductionCheck('delivery_regions', CheckStatus::Warning, 'No active delivery regions found.');
        } catch (\Throwable) {
            return new ProductionCheck('delivery_regions', CheckStatus::Fail, 'Could not query delivery regions.');
        }
    }

    protected function checkPaymentProvider(): ProductionCheck
    {
        $provider = (string) config('payments.provider', 'mock');

        if (app()->environment(['staging', 'production']) && $provider !== 'thawani') {
            return new ProductionCheck('payment_provider', CheckStatus::Fail, 'PAYMENT_PROVIDER must be thawani in staging/production.');
        }

        return new ProductionCheck('payment_provider', CheckStatus::Pass, "PAYMENT_PROVIDER is '{$provider}'.");
    }

    /**
     * @return list<ProductionCheck>
     */
    protected function thawaniConfigurationChecks(): array
    {
        $provider = (string) config('payments.provider', 'mock');

        if ($provider !== 'thawani') {
            return [
                new ProductionCheck('thawani_config', CheckStatus::Warning, 'Thawani configuration skipped (provider is not thawani).'),
            ];
        }

        return $this->thawaniChecker->run(connect: false);
    }

    /**
     * @return list<ProductionCheck>
     */
    protected function checkLegalRoutes(): array
    {
        $slugs = ['privacy', 'terms', 'returns', 'shipping', 'contact'];
        $checks = [];

        foreach ($slugs as $slug) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl()}/legal/{$slug}");
                if ($response->successful()) {
                    $checks[] = new ProductionCheck("legal_{$slug}", CheckStatus::Pass, "/legal/{$slug} is reachable.");
                } else {
                    $checks[] = new ProductionCheck("legal_{$slug}", CheckStatus::Fail, "/legal/{$slug} returned HTTP {$response->status()}.");
                }
            } catch (\Throwable) {
                $checks[] = new ProductionCheck("legal_{$slug}", CheckStatus::Fail, "/legal/{$slug} is unreachable.");
            }
        }

        return $checks;
    }

    protected function checkWebhookRoute(): ProductionCheck
    {
        $registered = collect(Route::getRoutes())->contains(
            fn ($route) => in_array('POST', $route->methods(), true)
                && str_contains($route->uri(), 'payments/webhook')
        );

        if ($registered) {
            return new ProductionCheck('webhook_route', CheckStatus::Pass, 'Payment webhook route is registered.');
        }

        return new ProductionCheck('webhook_route', CheckStatus::Fail, 'Payment webhook route is not registered.');
    }

    protected function checkHttpsCallbacks(): ProductionCheck
    {
        if (! app()->environment(['staging', 'production'])) {
            return new ProductionCheck('https_callbacks', CheckStatus::Warning, 'HTTPS callback check skipped outside staging/production.');
        }

        foreach ([
            'success_url' => config('payments.thawani.success_url'),
            'cancel_url' => config('payments.thawani.cancel_url'),
        ] as $label => $url) {
            $url = trim((string) $url);
            if ($url !== '' && ! str_starts_with($url, 'https://')) {
                return new ProductionCheck('https_callbacks', CheckStatus::Fail, "Thawani {$label} must use HTTPS.");
            }
        }

        $appUrl = $this->baseUrl();
        if ($appUrl !== '' && ! str_starts_with($appUrl, 'https://')) {
            return new ProductionCheck('https_callbacks', CheckStatus::Fail, 'APP_URL must use HTTPS in staging/production.');
        }

        return new ProductionCheck('https_callbacks', CheckStatus::Pass, 'HTTPS callback URLs are configured.');
    }

    protected function checkRequiredApiRoutes(): ProductionCheck
    {
        $required = [
            'api/health',
            'api/readiness',
            'api/customer/login',
            'api/customer/register',
            'api/products',
            'api/delivery/regions',
        ];

        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();
        $missing = [];

        foreach ($required as $fragment) {
            $found = false;
            foreach ($uris as $uri) {
                if ($uri === $fragment || str_ends_with($uri, $fragment)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $fragment;
            }
        }

        if ($missing === []) {
            return new ProductionCheck('api_routes', CheckStatus::Pass, 'Required API routes are registered.');
        }

        return new ProductionCheck('api_routes', CheckStatus::Fail, 'Missing routes: '.implode(', ', $missing));
    }

    protected function checkAuthenticatedCatalog(): ProductionCheck
    {
        $phone = trim((string) env('STAGING_CUSTOMER_PHONE', ''));
        $password = trim((string) env('STAGING_CUSTOMER_PASSWORD', ''));

        if ($phone === '' || $password === '') {
            return new ProductionCheck('auth_catalog', CheckStatus::Warning, 'STAGING_CUSTOMER_PHONE/PASSWORD not set; skipped authenticated catalog check.');
        }

        try {
            $login = Http::timeout(15)->post("{$this->baseUrl()}/api/customer/login", [
                'phone' => $phone,
                'password' => $password,
            ]);

            $token = $login->json('data.token') ?? $login->json('token');
            if (! is_string($token) || $token === '') {
                return new ProductionCheck('auth_catalog', CheckStatus::Fail, 'Staging customer login failed.');
            }

            $products = Http::timeout(15)
                ->withToken($token)
                ->get("{$this->baseUrl()}/api/products");

            if ($products->successful()) {
                return new ProductionCheck('auth_catalog', CheckStatus::Pass, 'Authenticated product catalog request succeeded.');
            }

            return new ProductionCheck('auth_catalog', CheckStatus::Fail, 'Authenticated product catalog request failed.');
        } catch (\Throwable) {
            return new ProductionCheck('auth_catalog', CheckStatus::Fail, 'Authenticated catalog check failed.');
        }
    }
}
