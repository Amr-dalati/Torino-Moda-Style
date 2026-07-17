<?php

namespace App\Support\Production;

class ThawaniReadinessChecker
{
    /**
     * @return list<ProductionCheck>
     */
    public function run(bool $connect = false): array
    {
        return array_merge(
            $this->configurationChecks(),
            $connect ? [$this->connectivityCheck()] : [],
        );
    }

    /**
     * @return list<ProductionCheck>
     */
    protected function configurationChecks(): array
    {
        $checks = [];
        $provider = (string) config('payments.provider', 'mock');

        if ($provider !== 'thawani') {
            $checks[] = new ProductionCheck('payment_provider', CheckStatus::Warning, 'PAYMENT_PROVIDER is not thawani; UAT checks are informational only.');

            return $checks;
        }

        $baseUrl = trim((string) config('payments.thawani.base_url', ''));
        if ($baseUrl === '' || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $checks[] = new ProductionCheck('thawani_base_url', CheckStatus::Fail, 'THAWANI_BASE_URL is missing or invalid.');
        } else {
            $checks[] = new ProductionCheck('thawani_base_url', CheckStatus::Pass, 'THAWANI_BASE_URL format is valid.');
        }

        foreach ([
            'thawani_secret_key' => 'THAWANI_SECRET_KEY',
            'thawani_publishable_key' => 'THAWANI_PUBLISHABLE_KEY',
            'thawani_webhook_secret' => 'THAWANI_WEBHOOK_SECRET',
        ] as $configKey => $envName) {
            $value = trim((string) config("payments.{$configKey}", ''));
            if ($value === '') {
                $checks[] = new ProductionCheck($configKey, CheckStatus::Fail, "{$envName} is missing.");
            } else {
                $checks[] = new ProductionCheck($configKey, CheckStatus::Pass, "{$envName} is configured.");
            }
        }

        foreach ([
            'success_url' => 'THAWANI_SUCCESS_URL',
            'cancel_url' => 'THAWANI_CANCEL_URL',
        ] as $key => $envName) {
            $url = trim((string) config("payments.thawani.{$key}", ''));
            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                $checks[] = new ProductionCheck("thawani_{$key}", CheckStatus::Fail, "{$envName} is missing or invalid.");
                continue;
            }

            if (! str_contains($url, '/payments/thawani/')) {
                $checks[] = new ProductionCheck("thawani_{$key}", CheckStatus::Warning, "{$envName} should point to backend /payments/thawani/* routes.");
            } else {
                $checks[] = new ProductionCheck("thawani_{$key}", CheckStatus::Pass, "{$envName} route shape looks valid.");
            }
        }

        $successMobile = trim((string) config('payments.mobile.payment_success_url', ''));
        $cancelMobile = trim((string) config('payments.mobile.payment_cancel_url', ''));
        if (! str_starts_with($successMobile, 'torinomodastyle://') || ! str_starts_with($cancelMobile, 'torinomodastyle://')) {
            $checks[] = new ProductionCheck('mobile_deep_links', CheckStatus::Fail, 'Mobile payment return URLs must use torinomodastyle:// scheme.');
        } else {
            $checks[] = new ProductionCheck('mobile_deep_links', CheckStatus::Pass, 'Mobile deep-link return URLs are configured.');
        }

        $checkoutHost = parse_url((string) config('payments.thawani.checkout_base_url', ''), PHP_URL_HOST);
        if ($checkoutHost) {
            $checks[] = new ProductionCheck('payment_allowed_host', CheckStatus::Pass, "Expected checkout host: {$checkoutHost}");
        }

        $checks[] = new ProductionCheck(
            'webhook_registration',
            CheckStatus::Warning,
            'Register POST /api/payments/webhook/thawani in the Thawani dashboard manually.',
        );

        return $checks;
    }

    protected function connectivityCheck(): ProductionCheck
    {
        $baseUrl = rtrim((string) config('payments.thawani.base_url', ''), '/');

        if ($baseUrl === '') {
            return new ProductionCheck('thawani_connectivity', CheckStatus::Fail, 'Cannot test connectivity without THAWANI_BASE_URL.');
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders([
                    'thawani-api-key' => (string) config('payments.thawani.secret_key', ''),
                ])
                ->get($baseUrl.'/checkout/session/nonexistent-probe');

            if (in_array($response->status(), [401, 403, 404, 422], true)) {
                return new ProductionCheck('thawani_connectivity', CheckStatus::Pass, 'Thawani API endpoint is reachable.');
            }

            if ($response->successful()) {
                return new ProductionCheck('thawani_connectivity', CheckStatus::Pass, 'Thawani API endpoint responded successfully.');
            }

            return new ProductionCheck('thawani_connectivity', CheckStatus::Warning, 'Thawani API returned an unexpected status code.');
        } catch (\Throwable) {
            return new ProductionCheck('thawani_connectivity', CheckStatus::Fail, 'Thawani API endpoint could not be reached.');
        }
    }
}
