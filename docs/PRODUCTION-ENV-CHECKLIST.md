# Production .env Checklist

## Application
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=base64:...` (set once; keep secret)
- `APP_URL=https://<your-domain>`
- `LOG_LEVEL=info` (or `warning` depending on ops preference)

## Database
- `DB_CONNECTION=mysql`
- `DB_HOST=...`
- `DB_PORT=3306`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

## Queue / Failed Jobs
- `QUEUE_CONNECTION=database` (or `redis` later, if adopted)
- `QUEUE_FAILED_DRIVER=database-uuids`

## Cache / Session
- `CACHE_STORE=database` (or `redis` later)
- `SESSION_DRIVER=database`
- If using cookies across subdomains:
  - `SESSION_DOMAIN=.example.com`

## Sanctum / API auth
Only set these if you intentionally use Sanctum in cookie/stateful mode (web SPA):
- `SANCTUM_STATEFUL_DOMAINS=...`
- `SESSION_DOMAIN=...`

For mobile-token usage only, keep stateful domains minimal/empty.

## Storage
- If using local disk uploads: ensure `storage/` writable
- If using S3 later: configure AWS env vars (not required now)

## Payment placeholders (future)
- Payment gateway secrets should be stored as env vars and **never logged**
- No real payment configuration is required yet in this project stage

## Phoenix placeholders (future)
- `PHOENIX_BASE_URL=...`
- `PHOENIX_API_KEY=...` (if used)
- `PHOENIX_USERNAME=...`
- `PHOENIX_PASSWORD=...`
- Ensure Phoenix credentials are kept secret and redacted from any logs

