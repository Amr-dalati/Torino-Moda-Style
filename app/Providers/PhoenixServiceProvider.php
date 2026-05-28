<?php

namespace App\Providers;

use App\Integrations\Phoenix\Contracts\PhoenixClientInterface;
use App\Integrations\Phoenix\Contracts\PhoenixProductServiceInterface;
use App\Integrations\Phoenix\Contracts\PhoenixStockServiceInterface;
use App\Integrations\Phoenix\Mock\MockPhoenixClient;
use App\Integrations\Phoenix\Mock\MockPhoenixProductService;
use App\Integrations\Phoenix\Mock\MockPhoenixStockService;
use App\Integrations\Phoenix\PhoenixClient;
use App\Integrations\Phoenix\Services\PhoenixProductService;
use App\Integrations\Phoenix\Services\PhoenixStockService;
use Illuminate\Support\ServiceProvider;

class PhoenixServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('phoenix.use_mock')) {
            $this->app->singleton(PhoenixClientInterface::class, MockPhoenixClient::class);
            $this->app->singleton(PhoenixProductServiceInterface::class, MockPhoenixProductService::class);
            $this->app->singleton(PhoenixStockServiceInterface::class, MockPhoenixStockService::class);
        } else {
            $this->app->singleton(PhoenixClientInterface::class, PhoenixClient::class);
            $this->app->singleton(PhoenixProductServiceInterface::class, PhoenixProductService::class);
            $this->app->singleton(PhoenixStockServiceInterface::class, PhoenixStockService::class);
        }
    }
}
