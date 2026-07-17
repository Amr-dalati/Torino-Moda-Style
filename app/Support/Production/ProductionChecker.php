<?php

namespace App\Support\Production;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductionChecker
{
    /**
     * @return list<ProductionCheck>
     */
    public function run(): array
    {
        return [
            $this->checkAppEnv(),
            $this->checkAppDebug(),
            $this->checkAppUrl(),
            $this->checkPaymentProvider(),
            $this->checkThawaniConfiguration(),
            $this->checkMobileReturnUrls(),
            $this->checkDatabaseDriver(),
            $this->checkCacheDriver(),
            $this->checkSessionDriver(),
            $this->checkQueueConnection(),
            $this->checkLoggingChannel(),
            $this->checkStorageSymlink(),
            $this->checkLowStockThreshold(),
            $this->checkPhoenixMode(),
            $this->checkPlaceholderValues(),
            $this->checkSchedulerInstructions(),
        ];
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

    protected function isProductionLike(): bool
    {
        return app()->environment(['staging', 'production']);
    }

    protected function checkAppEnv(): ProductionCheck
    {
        $env = (string) config('app.env', 'local');

        if (in_array($env, ['production', 'staging', 'local', 'testing'], true)) {
            return new ProductionCheck('app_env', CheckStatus::Pass, "APP_ENV is '{$env}'.");
        }

        return new ProductionCheck('app_env', CheckStatus::Warning, "APP_ENV '{$env}' is uncommon; verify deployment intent.");
    }

    protected function checkAppDebug(): ProductionCheck
    {
        $debug = (bool) config('app.debug');

        if ($this->isProductionLike() && $debug) {
            return new ProductionCheck('app_debug', CheckStatus::Fail, 'APP_DEBUG must be false in staging/production.');
        }

        if ($debug) {
            return new ProductionCheck('app_debug', CheckStatus::Warning, 'APP_DEBUG is enabled.');
        }

        return new ProductionCheck('app_debug', CheckStatus::Pass, 'APP_DEBUG is disabled.');
    }

    protected function checkAppUrl(): ProductionCheck
    {
        $url = trim((string) config('app.url', ''));

        if ($url === '' || $url === 'http://localhost') {
            $status = $this->isProductionLike() ? CheckStatus::Fail : CheckStatus::Warning;

            return new ProductionCheck('app_url', $status, 'APP_URL is missing or still uses a development placeholder.');
        }

        if ($this->isProductionLike() && ! str_starts_with($url, 'https://')) {
            return new ProductionCheck('app_url', CheckStatus::Fail, 'APP_URL must use HTTPS in staging/production.');
        }

        return new ProductionCheck('app_url', CheckStatus::Pass, 'APP_URL is configured.');
    }

    protected function checkPaymentProvider(): ProductionCheck
    {
        $provider = (string) config('payments.provider', 'mock');

        if ($this->isProductionLike() && $provider !== 'thawani') {
            return new ProductionCheck('payment_provider', CheckStatus::Fail, 'PAYMENT_PROVIDER must be thawani in staging/production.');
        }

        if ($provider === 'mock') {
            return new ProductionCheck('payment_provider', CheckStatus::Warning, 'PAYMENT_PROVIDER is mock (acceptable for local/testing).');
        }

        return new ProductionCheck('payment_provider', CheckStatus::Pass, "PAYMENT_PROVIDER is '{$provider}'.");
    }

    protected function checkThawaniConfiguration(): ProductionCheck
    {
        $provider = (string) config('payments.provider', 'mock');

        if ($provider !== 'thawani') {
            return new ProductionCheck('thawani_configuration', CheckStatus::Pass, 'Thawani keys not required while PAYMENT_PROVIDER is not thawani.');
        }

        $missing = [];
        $keys = [
            'THAWANI_SECRET_KEY' => 'payments.thawani.secret_key',
            'THAWANI_PUBLISHABLE_KEY' => 'payments.thawani.publishable_key',
            'THAWANI_WEBHOOK_SECRET' => 'payments.thawani.webhook_secret',
            'THAWANI_SUCCESS_URL' => 'payments.thawani.success_url',
            'THAWANI_CANCEL_URL' => 'payments.thawani.cancel_url',
        ];

        foreach ($keys as $envName => $configKey) {
            if (trim((string) config($configKey, '')) === '') {
                $missing[] = $envName;
            }
        }

        if ($missing !== []) {
            $status = $this->isProductionLike() ? CheckStatus::Fail : CheckStatus::Warning;

            return new ProductionCheck(
                'thawani_configuration',
                $status,
                'Missing Thawani configuration: '.implode(', ', $missing).'.',
            );
        }

        $successUrl = (string) config('payments.thawani.success_url');
        $cancelUrl = (string) config('payments.thawani.cancel_url');

        if ($this->isProductionLike()) {
            foreach ([$successUrl, $cancelUrl] as $url) {
                if (! str_starts_with($url, 'https://')) {
                    return new ProductionCheck('thawani_configuration', CheckStatus::Fail, 'Thawani success/cancel URLs must use HTTPS in staging/production.');
                }
            }
        }

        return new ProductionCheck('thawani_configuration', CheckStatus::Pass, 'Required Thawani configuration keys are present.');
    }

    protected function checkMobileReturnUrls(): ProductionCheck
    {
        $success = trim((string) config('payments.mobile.payment_success_url', ''));
        $cancel = trim((string) config('payments.mobile.payment_cancel_url', ''));

        if ($success === '' || $cancel === '') {
            return new ProductionCheck('mobile_return_urls', CheckStatus::Fail, 'MOBILE_PAYMENT_SUCCESS_URL and MOBILE_PAYMENT_CANCEL_URL must be configured.');
        }

        foreach ([$success, $cancel] as $url) {
            if (! str_starts_with($url, 'torinomodastyle://')) {
                return new ProductionCheck('mobile_return_urls', CheckStatus::Fail, 'Mobile payment return URLs must use the torinomodastyle:// scheme.');
            }
        }

        return new ProductionCheck('mobile_return_urls', CheckStatus::Pass, 'Mobile deep-link return URLs are configured.');
    }

    protected function checkDatabaseDriver(): ProductionCheck
    {
        $driver = (string) config('database.default', 'sqlite');

        if ($this->isProductionLike() && $driver === 'sqlite') {
            return new ProductionCheck('database_driver', CheckStatus::Fail, 'SQLite must not be used in staging/production.');
        }

        try {
            DB::connection()->getPdo();

            return new ProductionCheck('database_driver', CheckStatus::Pass, "Database driver '{$driver}' is reachable.");
        } catch (\Throwable) {
            return new ProductionCheck('database_driver', CheckStatus::Fail, "Database driver '{$driver}' is not reachable.");
        }
    }

    protected function checkCacheDriver(): ProductionCheck
    {
        $store = (string) config('cache.default', 'database');

        if ($this->isProductionLike() && $store === 'array') {
            return new ProductionCheck('cache_driver', CheckStatus::Fail, 'CACHE_STORE=array is not suitable for staging/production.');
        }

        try {
            Cache::store()->put('production_check_probe', 'ok', 10);
            $value = Cache::store()->get('production_check_probe');
            Cache::store()->forget('production_check_probe');

            if ($value !== 'ok') {
                return new ProductionCheck('cache_driver', CheckStatus::Fail, "Cache store '{$store}' failed a write/read probe.");
            }

            return new ProductionCheck('cache_driver', CheckStatus::Pass, "Cache store '{$store}' is operational.");
        } catch (\Throwable) {
            return new ProductionCheck('cache_driver', CheckStatus::Fail, "Cache store '{$store}' is not operational.");
        }
    }

    protected function checkSessionDriver(): ProductionCheck
    {
        $driver = (string) config('session.driver', 'file');

        if ($this->isProductionLike() && $driver === 'array') {
            return new ProductionCheck('session_driver', CheckStatus::Fail, 'SESSION_DRIVER=array is not suitable for staging/production.');
        }

        return new ProductionCheck('session_driver', CheckStatus::Pass, "SESSION_DRIVER is '{$driver}'.");
    }

    protected function checkQueueConnection(): ProductionCheck
    {
        $connection = (string) config('queue.default', 'sync');

        if ($this->isProductionLike() && $connection === 'sync') {
            return new ProductionCheck('queue_connection', CheckStatus::Warning, 'QUEUE_CONNECTION=sync; async queue worker is not configured.');
        }

        return new ProductionCheck('queue_connection', CheckStatus::Pass, "QUEUE_CONNECTION is '{$connection}'.");
    }

    protected function checkLoggingChannel(): ProductionCheck
    {
        $channel = (string) config('logging.default', 'stack');

        if ($channel === '') {
            return new ProductionCheck('logging_channel', CheckStatus::Fail, 'LOG_CHANNEL is not configured.');
        }

        return new ProductionCheck('logging_channel', CheckStatus::Pass, "LOG_CHANNEL is '{$channel}'.");
    }

    protected function checkStorageSymlink(): ProductionCheck
    {
        $publicStorage = public_path('storage');

        if (File::exists($publicStorage)) {
            return new ProductionCheck('storage_symlink', CheckStatus::Pass, 'public/storage is available.');
        }

        $status = $this->isProductionLike() ? CheckStatus::Warning : CheckStatus::Pass;

        return new ProductionCheck('storage_symlink', $status, 'public/storage symlink is missing; run php artisan storage:link.');
    }

    protected function checkLowStockThreshold(): ProductionCheck
    {
        $threshold = (int) config('inventory.low_stock_threshold', 5);

        if ($threshold < 0) {
            return new ProductionCheck('low_stock_threshold', CheckStatus::Fail, 'LOW_STOCK_THRESHOLD must be zero or greater.');
        }

        return new ProductionCheck('low_stock_threshold', CheckStatus::Pass, "LOW_STOCK_THRESHOLD is {$threshold}.");
    }

    protected function checkPhoenixMode(): ProductionCheck
    {
        $useMock = (bool) config('phoenix.use_mock', true);

        if ($this->isProductionLike() && $useMock) {
            return new ProductionCheck('phoenix_mode', CheckStatus::Warning, 'PHOENIX_USE_MOCK=true; Phoenix integration is in mock mode.');
        }

        if ($useMock) {
            return new ProductionCheck('phoenix_mode', CheckStatus::Pass, 'Phoenix mock mode is enabled (local/testing).');
        }

        return new ProductionCheck('phoenix_mode', CheckStatus::Pass, 'Phoenix live integration mode is configured.');
    }

    protected function checkPlaceholderValues(): ProductionCheck
    {
        $issues = [];

        $appKey = (string) config('app.key', '');
        if ($appKey === '') {
            $issues[] = 'APP_KEY';
        }

        $webhookSecret = trim((string) config('payments.thawani.webhook_secret', ''));
        if ($webhookSecret !== '' && in_array($webhookSecret, ['mock-webhook-secret', 'changeme', 'test'], true)) {
            $issues[] = 'THAWANI_WEBHOOK_SECRET (placeholder value)';
        }

        $mockSecret = trim((string) config('payments.mock.webhook_secret', ''));
        if ($this->isProductionLike() && $mockSecret === 'mock-webhook-secret') {
            $issues[] = 'MOCK_PAYMENT_WEBHOOK_SECRET (unsafe default)';
        }

        if ($issues !== []) {
            return new ProductionCheck('placeholder_values', CheckStatus::Fail, 'Placeholder or unsafe values detected: '.implode(', ', $issues).'.');
        }

        return new ProductionCheck('placeholder_values', CheckStatus::Pass, 'No obvious placeholder secrets detected.');
    }

    protected function checkSchedulerInstructions(): ProductionCheck
    {
        return new ProductionCheck(
            'scheduler_cron',
            CheckStatus::Pass,
            'Configure cron: * * * * * php /path/to/artisan schedule:run',
        );
    }
}
