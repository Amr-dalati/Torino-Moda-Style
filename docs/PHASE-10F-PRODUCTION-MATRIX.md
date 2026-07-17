# Phase 10F — Production Configuration Matrix

This matrix summarizes environment-dependent settings for controlled Thawani UAT and production release. Values shown are **examples only** — never commit real secrets.

## Backend (Laravel)

| Setting | Local / Dev | Staging / UAT | Production | Validation |
|---------|-------------|---------------|------------|------------|
| `APP_ENV` | `local` | `staging` | `production` | `php artisan app:production-check` |
| `APP_DEBUG` | `true` | `false` | `false` | FAIL if true in staging/prod |
| `APP_URL` | `http://localhost` | `https://api.example.com` | `https://api.example.com` | Must be HTTPS in staging/prod |
| `PAYMENT_PROVIDER` | `mock` | `thawani` | `thawani` | FAIL if not thawani in staging/prod |
| `THAWANI_BASE_URL` | UAT URL | `https://uatcheckout.thawani.om/api/v1` | Production Thawani URL | `payments:thawani-check` |
| `THAWANI_PUBLISHABLE_KEY` | — | From Thawani dashboard | From Thawani dashboard | Presence only (never printed) |
| `THAWANI_SECRET_KEY` | — | From Thawani dashboard | From Thawani dashboard | Presence only |
| `THAWANI_WEBHOOK_SECRET` | — | Strong random secret | Strong random secret | Presence only |
| `THAWANI_SUCCESS_URL` | — | `https://api.example.com/payments/thawani/success` | Same pattern | Must be HTTPS backend route |
| `THAWANI_CANCEL_URL` | — | `https://api.example.com/payments/thawani/cancel` | Same pattern | Must be HTTPS backend route |
| `MOBILE_PAYMENT_SUCCESS_URL` | `torinomodastyle://payment/success` | Same | Same | Custom scheme required |
| `MOBILE_PAYMENT_CANCEL_URL` | `torinomodastyle://payment/cancel` | Same | Same | Custom scheme required |
| `DB_CONNECTION` | `sqlite` | `mysql` / `pgsql` | `mysql` / `pgsql` | FAIL if sqlite in staging/prod |
| `CACHE_STORE` | `database` | `redis` recommended | `redis` recommended | WARNING if file/database in prod |
| `SESSION_DRIVER` | `database` | `redis` / `database` | `redis` / `database` | WARNING if misconfigured |
| `QUEUE_CONNECTION` | `database` / `sync` | `redis` / `database` | `redis` / `database` | WARNING if `sync` in prod |
| `LOG_CHANNEL` | `stack` | `stack` / `daily` | `stack` / `daily` | Valid channel required |
| `LOW_STOCK_THRESHOLD` | `5` | `5` (adjust) | `5` (adjust) | Must be positive integer |
| `PHOENIX_USE_MOCK` | `true` | `false` when live | `false` when live | Reported clearly |
| Legal placeholders | Defaults OK | Configure | Configure | `LEGAL_*` env vars |

### Trusted proxy / HTTPS

- Terminate TLS at the reverse proxy (nginx, load balancer, Cloudflare).
- Set `APP_URL` to the public HTTPS origin.
- Ensure proxy forwards `X-Forwarded-Proto: https` when applicable.

### Scheduler (production cron)

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks:

- `payments:expire-pending` — every 5 minutes (idempotent unpaid-order expiry)
- `ops:scheduler-heartbeat` — every minute (readiness freshness signal)

### Health endpoints

| Endpoint | Purpose | Access |
|----------|---------|--------|
| `GET /api/health` | Liveness — process is running | Public, minimal JSON |
| `GET /api/readiness` | Readiness — DB, cache, storage, payments, scheduler | Public, component status only |

Readiness returns `{ status, checks: [{ name, status }] }` without secrets, stack traces, or environment details.

## Flutter (Mobile)

| Define | Dev | Staging / UAT | Production | Validation |
|--------|-----|---------------|------------|------------|
| `APP_ENV` | `dev` | `staging` | `prod` | Startup `Env.load()` |
| `API_BASE_URL` | `http://127.0.0.1:8000/api` | `https://api.example.com/api` | `https://api.example.com/api` | HTTPS required in staging/prod |
| `PAYMENT_ALLOWED_HOSTS` | Optional (defaults) | `uatcheckout.thawani.om` | Production Thawani host | Required in staging/prod |
| `LEGAL_BASE_URL` | Optional (derived from API) | `https://api.example.com/legal` | `https://api.example.com/legal` | HTTPS + trusted host |
| `SENTRY_DSN` | Empty OK | Set when ready | Set when ready | Warning if missing in staging/prod |

### Example build defines

**Staging / UAT:**

```bash
flutter build apk --release \
  --dart-define=APP_ENV=staging \
  --dart-define=API_BASE_URL=https://api.example.com/api \
  --dart-define=PAYMENT_ALLOWED_HOSTS=uatcheckout.thawani.om \
  --dart-define=LEGAL_BASE_URL=https://api.example.com/legal
```

### App identity (manual decisions before store release)

| Platform | Current placeholder | Action before release |
|----------|--------------------|-----------------------|
| Android `applicationId` | `com.torinomodastyle.app` | Set in Phase 10G — **cannot change after Play publish** |
| Android display name | `Torino Moda Style` | OK |
| iOS bundle ID | `com.torinomodastyle.app` | Set in Xcode (Phase 10G) |
| iOS display name | `Torino Moda Style` | OK |
| Deep-link scheme | `torinomodastyle` | Register in Thawani + app manifests |

## Validation commands

```bash
# Backend
php artisan app:production-check
php artisan payments:thawani-check
php artisan payments:thawani-check --connect   # optional connectivity only

# Flutter (local)
flutter analyze
flutter test
```

## Related documentation

- [THAWANI-UAT-CHECKLIST.md](./THAWANI-UAT-CHECKLIST.md)
- [UAT-TEST-PLAN.md](./UAT-TEST-PLAN.md)
- [BACKUP-RUNBOOK.md](./BACKUP-RUNBOOK.md)
- [OPS-QUEUE-SCHEDULER.md](./OPS-QUEUE-SCHEDULER.md)
- [PRODUCTION-ENV-CHECKLIST.md](./PRODUCTION-ENV-CHECKLIST.md)
- Mobile: `torino-moda-style-mobile/docs/ENVIRONMENTS.md`, `RELEASE_BUILDS.md`, `ANDROID_SIGNING.md`
