<?php

namespace App\Integrations\Phoenix\Mock;

use App\Integrations\Phoenix\Contracts\PhoenixStockServiceInterface;
use App\Integrations\Phoenix\Services\PhoenixStockService;

/**
 * Uses MockPhoenixClient via the shared PhoenixStockService implementation.
 */
class MockPhoenixStockService extends PhoenixStockService implements PhoenixStockServiceInterface
{
    public function __construct(MockPhoenixClient $client)
    {
        parent::__construct($client);
    }
}

