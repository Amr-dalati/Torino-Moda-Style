# Deployment Runbook

This runbook describes a **safe, repeatable** path to deploy the Torino Moda Style backend.

## Preconditions
- Production `.env` prepared (see `docs/PRODUCTION-ENV-CHECKLIST.md`)
- Database credentials and backups verified
- A plan for running **queue workers** and (future) **scheduler**
- Web server configured (Nginx/Apache) with correct document root (`public/`)

## Standard deployment steps (git-based)
1. Put the app in maintenance mode (optional but recommended):

```bash
php artisan down
```

2. Fetch and update code:

```bash
git fetch --all --prune
git pull
```

3. Install PHP dependencies (production):

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

4. Ensure runtime directories are writable:
- `storage/`
- `bootstrap/cache/`

5. Run migrations:

```bash
php artisan migrate --force
```

6. Cache for performance:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Restart services
- PHP-FPM reload / app container restart
- Queue worker restart (if running):

```bash
php artisan queue:restart
```

8. Bring app back up:

```bash
php artisan up
```

9. Post-deploy smoke checks
- `GET /api/health` returns `success=true`
- Admin panel `/admin` reachable (auth enforced)

## Migration strategy notes
- Prefer **backward-compatible** migrations.
- For large tables, schedule migrations during low traffic windows.
- Always take a **pre-deploy backup** (see `docs/BACKUP-RUNBOOK.md`).

## Queue worker notes (current readiness)
See `docs/OPS-QUEUE-SCHEDULER.md` for worker commands.

## Scheduler notes (future)
No cron-dependent business logic is required yet.
When scheduler tasks are introduced, run scheduler via:
- `php artisan schedule:work`, or
- system cron `schedule:run` every minute

## Rollback plan
Rollback depends on whether migrations are reversible.

Recommended rollback procedure:
1. Put app in maintenance mode:

```bash
php artisan down
```

2. Roll back code to last known good commit/tag.
3. If needed and safe, roll back migrations:

```bash
php artisan migrate:rollback --force
```

4. Restore database backup if rollback migrations are not sufficient.
5. Clear and rebuild caches:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Bring app back up:

```bash
php artisan up
```

