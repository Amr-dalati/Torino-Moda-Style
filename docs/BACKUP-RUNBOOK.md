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
  - basic login flows
  - admin panel access

