<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThawaniCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_thawani_check_runs_with_mock_provider(): void
    {
        config(['payments.provider' => 'mock']);

        $this->artisan('payments:thawani-check')
            ->assertExitCode(0)
            ->expectsOutputToContain('Thawani payment readiness check');
    }

    public function test_thawani_check_reports_missing_keys_when_provider_is_thawani(): void
    {
        config([
            'payments.provider' => 'thawani',
            'payments.thawani.secret_key' => '',
            'payments.thawani.publishable_key' => '',
            'payments.thawani.webhook_secret' => '',
        ]);

        $this->artisan('payments:thawani-check')
            ->assertExitCode(1);
    }
}
