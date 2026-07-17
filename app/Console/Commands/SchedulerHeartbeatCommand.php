<?php

namespace App\Console\Commands;

use App\Support\Ops\SchedulerHeartbeat;
use Illuminate\Console\Command;

class SchedulerHeartbeatCommand extends Command
{
    protected $signature = 'ops:scheduler-heartbeat';

    protected $description = 'Record scheduler heartbeat for readiness monitoring';

    public function handle(SchedulerHeartbeat $heartbeat): int
    {
        $heartbeat->touch();

        $this->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
