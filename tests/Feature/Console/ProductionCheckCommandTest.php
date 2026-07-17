<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_runs_in_testing_environment(): void
    {
        $this->artisan('app:production-check')
            ->assertExitCode(0);
    }

    public function test_production_check_fails_when_debug_enabled_in_production(): void
    {
        $originalEnv = $this->app['env'];
        config([
            'app.env' => 'production',
            'app.debug' => true,
        ]);
        $this->app['env'] = 'production';

        try {
            $this->artisan('app:production-check')
                ->assertExitCode(1);
        } finally {
            config(['app.env' => 'local', 'app.debug' => true]);
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_production_check_output_does_not_include_secret_values(): void
    {
        config(['payments.mock.webhook_secret' => 'super-secret-value']);

        $this->artisan('app:production-check')
            ->expectsOutputToContain('PASS')
            ->doesntExpectOutputToContain('super-secret-value');
    }
}
