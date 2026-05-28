<?php

namespace App\Providers;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Integrations\Payments\MockPaymentGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayInterface::class, MockPaymentGateway::class);
    }
}

