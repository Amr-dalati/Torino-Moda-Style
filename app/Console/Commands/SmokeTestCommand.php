<?php

namespace App\Console\Commands;

use App\Support\Ops\SmokeTestRunner;
use App\Support\Production\CheckStatus;
use Illuminate\Console\Command;

class SmokeTestCommand extends Command
{
    protected $signature = 'app:smoke-test {--with-auth : Run optional authenticated catalog check using STAGING_CUSTOMER_* env vars}';

    protected $description = 'Non-destructive staging smoke test without exposing secrets';

    public function handle(SmokeTestRunner $runner): int
    {
        if (app()->environment('production')) {
            $this->warn('Running smoke test against production environment. Proceed only for controlled verification.');
        }

        $checks = $runner->run((bool) $this->option('with-auth'));

        $this->info('Staging smoke test');
        $this->newLine();

        foreach ($checks as $check) {
            $label = str_pad($check->name, 24);
            $status = match ($check->status) {
                CheckStatus::Pass => '<fg=green>PASS</>',
                CheckStatus::Warning => '<fg=yellow>WARNING</>',
                CheckStatus::Fail => '<fg=red>FAIL</>',
            };

            $this->line("{$label} {$status}  {$check->message}");
        }

        $this->newLine();

        if ($runner->hasFailures($checks)) {
            $this->error('One or more critical smoke checks failed.');

            return self::FAILURE;
        }

        $this->info('Smoke test completed successfully.');

        return self::SUCCESS;
    }
}
