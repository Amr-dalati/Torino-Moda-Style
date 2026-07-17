<?php

namespace App\Providers;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Integrations\Payments\PaymentGatewayResolver;
use App\Support\PaymentSecrets;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayResolver::class);

        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            /** @var PaymentGatewayResolver $resolver */
            $resolver = $app->make(PaymentGatewayResolver::class);

            return $resolver->resolve(config('payments.provider'));
        });
    }

    public function boot(): void
    {
        PaymentSecrets::assertProductionReady();
    }
}
