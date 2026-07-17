<?php

namespace App\Services\Ops;

use App\Support\Ops\SchedulerHeartbeat;
use App\Support\PaymentSecrets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ReadinessService
{
    public function __construct(
        protected SchedulerHeartbeat $schedulerHeartbeat,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   checks: list<array{name: string, status: string}>
     * }
     */
    public function assess(): array
    {
        $checks = [
            $this->database(),
            $this->cache(),
            $this->storage(),
            $this->paymentConfiguration(),
            $this->queue(),
            $this->scheduler(),
        ];

        $overall = 'ready';
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $overall = 'not_ready';
                break;
            }

            if ($check['status'] === 'warn' && $overall === 'ready') {
                $overall = 'degraded';
            }
        }

        return [
            'status' => $overall,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function database(): array
    {
        try {
            DB::connection()->getPdo();

            return ['name' => 'database', 'status' => 'ok'];
        } catch (\Throwable) {
            return ['name' => 'database', 'status' => 'fail'];
        }
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function cache(): array
    {
        try {
            Cache::store()->put('readiness_probe', 'ok', 10);
            $value = Cache::store()->get('readiness_probe');
            Cache::store()->forget('readiness_probe');

            return ['name' => 'cache', 'status' => $value === 'ok' ? 'ok' : 'fail'];
        } catch (\Throwable) {
            return ['name' => 'cache', 'status' => 'fail'];
        }
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function storage(): array
    {
        try {
            Storage::disk(config('filesystems.default', 'local'))->put('readiness_probe.txt', 'ok');
            Storage::disk(config('filesystems.default', 'local'))->delete('readiness_probe.txt');

            $publicLink = public_path('storage');
            $status = File::exists($publicLink) ? 'ok' : 'warn';

            return ['name' => 'storage', 'status' => $status];
        } catch (\Throwable) {
            return ['name' => 'storage', 'status' => 'fail'];
        }
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function paymentConfiguration(): array
    {
        if (! app()->environment(['staging', 'production'])) {
            return ['name' => 'payments', 'status' => 'ok'];
        }

        try {
            PaymentSecrets::assertProductionReady();

            return ['name' => 'payments', 'status' => 'ok'];
        } catch (\Throwable) {
            return ['name' => 'payments', 'status' => 'fail'];
        }
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function queue(): array
    {
        $connection = (string) config('queue.default', 'sync');

        if (app()->environment(['staging', 'production']) && $connection === 'sync') {
            return ['name' => 'queue', 'status' => 'warn'];
        }

        return ['name' => 'queue', 'status' => 'ok'];
    }

    /**
     * @return array{name: string, status: string}
     */
    protected function scheduler(): array
    {
        if (app()->environment(['local', 'testing'])) {
            return ['name' => 'scheduler', 'status' => 'ok'];
        }

        return [
            'name' => 'scheduler',
            'status' => $this->schedulerHeartbeat->isFresh() ? 'ok' : 'warn',
        ];
    }
}
