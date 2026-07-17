<?php

namespace App\Console\Commands;

use App\Support\Production\CheckStatus;
use App\Support\Production\ProductionChecker;
use App\Support\Production\ThawaniReadinessChecker;
use Illuminate\Console\Command;

class ThawaniCheckCommand extends Command
{
    protected $signature = 'payments:thawani-check {--connect : Perform a harmless authenticated connectivity probe}';

    protected $description = 'Validate Thawani UAT/production payment configuration without exposing secrets';

    public function handle(ThawaniReadinessChecker $checker, ProductionChecker $productionChecker): int
    {
        $checks = $checker->run((bool) $this->option('connect'));

        $this->info('Thawani payment readiness check');
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

        if ($productionChecker->hasFailures($checks)) {
            $this->error('One or more Thawani checks failed.');

            return self::FAILURE;
        }

        $this->info('Thawani readiness checks completed.');

        return self::SUCCESS;
    }
}
