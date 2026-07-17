# Backup Runbook

This runbook provides operational guidance for **backups and restores**.

## Pre-deployment backup checklist
- Confirm current app version/commit being deployed
- Confirm DB target (host/db name)
- Confirm backup destination has enough disk space
- Take DB backup
- If using user uploads or local storage, back up `storage/app/` and `public/storage` (if used)

## Database backup examples

### MySQL (recommended in production)

```bash
mysqldump --single-transaction --routines --triggers --hex-blob \
  -h <host> -u <user> -p <db_name> > backup_$(date +%F_%H%M%S).sql
```

Compression:

```bash
mysqldump --single-transaction -h <host> -u <user> -p <db_name> | gzip > backup_$(date +%F_%H%M%S).sql.gz
```

### Restore (MySQL)

```bash
mysql -h <host> -u <user> -p <db_name> < backup.sql
```

## Storage backup notes
If the app stores files locally:
- back up `storage/app/`
- back up `public/storage/` if you use the public symlink

If storage is on S3 or similar:
- rely on bucket versioning / lifecycle policies as appropriate

## Retention policy (suggestion)
- Keep daily backups for 7–14 days
- Keep weekly backups for 4–8 weeks
- Keep monthly backups for 6–12 months (if required)

## Restore checklist
- Put application into maintenance mode
- Restore database into target environment
- Restore storage files (if applicable)
- Run migrations only if matching code requires it (avoid drifting schema)
- Clear caches:

```bash
php artisan optimize:clear
```

- Verify:
  - `GET /api/health`
  - `GET /api/readiness`
  - basic login flows
  - admin panel access

## Environment file backup policy
- Store `.env` backups in a secrets manager or encrypted offline store — **never** in git
- Rotate `APP_KEY`, Thawani keys, and webhook secrets after compromise
- Document which environment each backup belongs to

## Backup encryption
- Encrypt database dumps at rest (e.g. `gpg`, cloud provider KMS)
- Restrict backup bucket access to operations staff only

## Rollback process

### Application rollback
1. Enable maintenance mode: `php artisan down`
2. Deploy previous known-good release artifact / git tag
3. Restore database backup if schema or data migration is not backward compatible
4. `php artisan optimize:clear`
5. Verify health/readiness endpoints
6. `php artisan up`

### Flutter rollback
- Publish previous store version (Play Console / App Store Connect)
- Ensure previous build's `API_BASE_URL` and payment hosts still match backend

## Thawani incident handling
- If payments fail broadly: check Thawani status, webhook logs, `THAWANI_*` env vars
- Do not change success/cancel URLs without updating Thawani dashboard
- Reconcile stuck orders via admin panel + `payments:expire-pending` if scheduler was down

## Webhook outage handling
- Customers can still return via mobile deep link; polling reconciles payment status
- Replay webhooks from Thawani dashboard when available
- Monitor `payment_webhook_events` / order payment status in Filament

## Scheduler outage handling
- Cron must run every minute: `* * * * * php /path/to/artisan schedule:run`
- If heartbeat is stale, readiness reports scheduler as degraded
- Manually run: `php artisan payments:expire-pending`

## Laravel maintenance mode

```bash
php artisan down --secret="maintenance-bypass-token"
# deploy / restore
php artisan up
```

## Database migration procedure (production)
1. Backup database
2. Put app in maintenance mode (optional, for breaking migrations)
3. `php artisan migrate --force`
4. Smoke test health, login, checkout read paths
5. Disable maintenance mode

