# Queue & Scheduler Readiness (Phase 3.10)

This project is **queue-ready** but currently keeps all business flows **synchronous**.

## Current state
- **Queue driver default**: `database` (`QUEUE_CONNECTION=database`)
- **Tables present**: `jobs`, `job_batches`, `failed_jobs` (created by `0001_01_01_000002_create_jobs_table.php`)
- **Failed jobs driver**: `database-uuids` (`QUEUE_FAILED_DRIVER=database-uuids`)
- **No current business flow depends on queues** (checkout/payment remain synchronous).

## Recommended operational commands (production)
- Start queue worker:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=5
```

- Monitor failed jobs:

```bash
php artisan queue:failed
php artisan queue:failed:show <uuid>
```

- Retry a failed job (when future jobs exist):

```bash
php artisan queue:retry <uuid>
```

## Scheduler (future)
When scheduler-driven tasks are introduced (webhook reprocessing, Phoenix incremental sync, cleanup), run:

```bash
php artisan schedule:work
```

Or via cron (Linux), every minute:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

## Environment checklist
- `QUEUE_CONNECTION=database` (or `redis` later, if adopted)
- `QUEUE_FAILED_DRIVER=database-uuids`
- Ensure migrations run so `jobs/failed_jobs/job_batches` exist
- Run at least one long-running worker process (supervisor/systemd/container)

## Deferred on purpose (not implemented in Phase 3.10)
- No payment webhook retry job
- No Phoenix sync jobs
- No notifications jobs
- No Horizon

