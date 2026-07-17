# Queue & Scheduler Operations (Phase 10F)

## Current queue requirements

| Component | Required now? | Notes |
|-----------|---------------|-------|
| `queue:work` | **No** for core checkout | Checkout and payment flows are synchronous |
| `QUEUE_CONNECTION=database` | Supported | Tables exist; worker optional until async jobs added |
| Failed jobs table | Yes | Monitor when jobs are introduced |

Future candidates for queues (not implemented):

- Payment webhook retry processing
- Phoenix incremental sync
- Email / notification dispatch
- Heavy image processing

When async jobs are added, run:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=5
```

## Scheduler (required in staging/production)

Cron entry — **every minute**:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

### Scheduled commands

| Command | Frequency | Purpose | Idempotent? |
|---------|-----------|---------|-------------|
| `payments:expire-pending` | Every 5 minutes | Expire unpaid orders past payment window; release reserved stock | Yes — safe to re-run |
| `ops:scheduler-heartbeat` | Every minute | Updates cache key `ops:scheduler:last_run_at` for readiness | Yes |

### `payments:expire-pending` safety

- Only affects **unpaid** pending payments past expiry
- Does **not** release stock for paid or processing orders
- Handles failures per-order without aborting entire batch
- Manual run: `php artisan payments:expire-pending`

### Scheduler heartbeat

- Cache key: `ops:scheduler:last_run_at`
- Readiness endpoint checks freshness in non-local environments
- If stale (> 3 minutes), readiness reports scheduler as `degraded`

## Readiness checks

`GET /api/readiness` verifies (component name + status only):

- Database connectivity
- Cache availability
- Storage / public disk
- Payment configuration (Thawani keys present in staging/prod)
- Queue configuration (warns if `sync` in production)
- Scheduler freshness (non-local)

## Environment checklist

- `QUEUE_CONNECTION=database` or `redis` (not `sync` in production)
- Cron configured for `schedule:run`
- Verify: `php artisan schedule:list`
- After deploy: `GET /api/readiness` should show `ready` or document `degraded` items

## Related commands

```bash
php artisan schedule:list
php artisan payments:expire-pending
php artisan ops:scheduler-heartbeat
php artisan queue:failed
```
