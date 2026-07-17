<?php

namespace App\Console\Commands;

use App\Support\Production\CheckStatus;
use App\Support\Production\ProductionChecker;
use Illuminate\Console\Command;

class ProductionCheckCommand extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Validate production-oriented configuration without exposing secrets';

    public function handle(ProductionChecker $checker): int
    {
        $checks = $checker->run();

        $this->info('Production configuration check');
        $this->newLine();

        foreach ($checks as $check) {
            $label = str_pad($check->name, 22);
            $status = match ($check->status) {
                CheckStatus::Pass => '<fg=green>PASS</>',
                CheckStatus::Warning => '<fg=yellow>WARNING</>',
                CheckStatus::Fail => '<fg=red>FAIL</>',
            };

            $this->line("{$label} {$status}  {$check->message}");
        }

        $this->newLine();

        if ($checker->hasFailures($checks)) {
            $this->error('One or more critical checks failed.');

            return self::FAILURE;
        }

        $this->info('All critical checks passed.');

        return self::SUCCESS;
    }
}
