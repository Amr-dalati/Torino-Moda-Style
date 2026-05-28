<?php

namespace App\Integrations\Phoenix\Mock;

use App\Integrations\Phoenix\Contracts\PhoenixProductServiceInterface;
use App\Integrations\Phoenix\Services\PhoenixProductService;

/**
 * Uses MockPhoenixClient via the shared PhoenixProductService implementation.
 */
class MockPhoenixProductService extends PhoenixProductService implements PhoenixProductServiceInterface
{
    public function __construct(MockPhoenixClient $client)
    {
        parent::__construct($client);
    }
}
