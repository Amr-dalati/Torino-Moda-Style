# Staging Deployment Runbook

Safe, repeatable workflow for deploying the Torino Moda Style backend to a **staging** environment. Replace `<app-root>` and `<release-ref>` with your server paths and git ref.

## Preconditions

- Staging server with PHP 8.2+, Composer, MySQL/PostgreSQL, Redis (recommended)
- `.env` created from [`.env.staging.example`](../.env.staging.example) — **never commit real secrets**
- TLS certificate on staging API host
- Cron configured: `* * * * * php <app-root>/artisan schedule:run`
- Pre-deploy database backup plan ([BACKUP-RUNBOOK.md](./BACKUP-RUNBOOK.md))

## Deployment steps

### 1. Pull approved code

```bash
cd <app-root>
git fetch --all --prune
git checkout <release-ref>
git pull --ff-only
```

### 2. Install dependencies

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

### 3. Set permissions

```bash
chmod -R ug+rwx storage bootstrap/cache
```

### 4. Configure environment

- Copy `.env.staging.example` → `.env` on the server (first deploy only)
- Set `APP_KEY`, database credentials, Thawani UAT keys, `STAGING_*` seed credentials
- **New environment only:** `php artisan key:generate`

### 5. Validate configuration

```bash
php artisan app:production-check
php artisan payments:thawani-check
```

Resolve FAIL items before continuing.

### 6. Maintenance mode

```bash
php artisan down --secret="staging-bypass-token"
```

### 7. Backup database

```bash
# Example — adjust for your DB engine
mysqldump --single-transaction -h <host> -u <user> -p <db_name> > backup_pre_deploy_$(date +%F_%H%M).sql
```

### 8. Run migrations

```bash
php artisan migrate --force
```

### 9. Clear and cache

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 10. Storage link

```bash
php artisan storage:link
```

### 11. Restart PHP-FPM

```bash
# Example — adjust for your stack
sudo systemctl reload php8.2-fpm
```

### 12. Queue workers (if used)

```bash
php artisan queue:restart
# Ensure a supervisor/systemd worker is running if async jobs are introduced
```

### 13. Scheduler heartbeat (manual verification)

```bash
php artisan ops:scheduler-heartbeat
```

### 14. Staging seed (first deploy or when resetting UAT data)

```bash
# Requires STAGING_CUSTOMER_PHONE and STAGING_CUSTOMER_PASSWORD in .env
php artisan db:seed --class=StagingSeeder --force
```

### 15. Disable maintenance mode

```bash
php artisan up
```

### 16. Smoke checks

```bash
php artisan app:smoke-test --with-auth
curl -fsS https://staging-api.example.com/api/health
curl -fsS https://staging-api.example.com/api/readiness
```

### 17. Manual smoke tests

- Customer login via mobile staging build
- Product list loads
- Legal pages `/legal/privacy` and `/legal/terms`
- Filament admin login

## Rollback

1. `php artisan down`
2. Restore database backup from step 7
3. `git checkout <previous-release-ref>`
4. `composer install --no-dev --prefer-dist --optimize-autoloader`
5. `php artisan migrate --force` (only if rollback commit requires it)
6. `php artisan optimize:clear && php artisan config:cache`
7. `php artisan up`
8. Re-run `php artisan app:smoke-test`

## Helper script (optional)

See [`scripts/staging-deploy.example.sh`](../scripts/staging-deploy.example.sh) for a non-destructive template. Customize paths on the server; do not commit server-specific secrets.

## Related docs

- [`.env.staging.example`](../.env.staging.example)
- [THAWANI-UAT-EXECUTION.md](./THAWANI-UAT-EXECUTION.md)
- [WEBHOOK-REACHABILITY.md](./WEBHOOK-REACHABILITY.md)
- [OPS-QUEUE-SCHEDULER.md](./OPS-QUEUE-SCHEDULER.md)
