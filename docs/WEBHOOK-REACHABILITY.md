# Webhook Reachability Verification

How to confirm the Thawani payment webhook is correctly configured **without weakening authentication**.

## Registered route

The application registers:

```text
POST /api/payments/webhook/{provider}
```

For Thawani UAT, the provider segment is `thawani`:

```text
POST https://<APP_URL>/api/payments/webhook/thawani
```

Verify route registration:

```bash
php artisan route:list --path=payments/webhook
php artisan app:smoke-test
```

The smoke test confirms the route exists internally. External reachability must be verified separately.

## HTTPS URL format

Thawani dashboard webhook URL must be:

- **HTTPS** only in staging/production
- Publicly reachable from Thawani servers
- Match `APP_URL` host (no localhost, no private IP)

```bash
php artisan payments:thawani-check
```

## Authentication — no bypass

- Webhook requests **must** include a valid signature validated against `THAWANI_WEBHOOK_SECRET`.
- **Do not** add unsigned test endpoints or disable signature validation for UAT.
- Readiness and health endpoints **do not** expose webhook secrets.

## Throttling

The webhook route uses `throttle:60,1` (60 requests per minute per IP). This should not block legitimate Thawani traffic under normal conditions. If throttling issues occur during UAT, investigate duplicate replays rather than removing throttling.

## External reachability checks

### 1. Thawani merchant dashboard

Register the full HTTPS webhook URL in the Thawani UAT merchant portal. Use the dashboard's test/replay feature when available.

### 2. Controlled replay (UAT only)

After a real UAT payment, use Thawani's dashboard to replay a webhook. Confirm:

- HTTP 200 response from your server
- Payment status updates in Filament
- No duplicate stock commit on replay

### 3. Server access logs

Confirm POST requests from Thawani IP ranges reach your reverse proxy:

- Check nginx/apache access logs for `POST /api/payments/webhook/thawani`
- Confirm TLS termination forwards `X-Forwarded-Proto: https`

### 4. Firewall / WAF

Ensure:

- Port 443 open to the internet
- WAF does not block POST bodies from payment providers
- No geo-blocking that excludes Thawani infrastructure

## Readiness interaction

`GET /api/readiness` reports component health only — it does **not** prove external webhook delivery. Combine:

```bash
php artisan app:smoke-test
curl -fsS https://staging-api.example.com/api/readiness
```

## Incident: webhook outage

If webhooks are delayed or unavailable:

1. Customer may still return via **mobile deep link**
2. Flutter **payment polling** reconciles status from backend
3. When webhooks recover, replay from Thawani dashboard for stuck orders
4. Run `php artisan payments:expire-pending` if scheduler was also down

## Related commands

```bash
php artisan payments:thawani-check
php artisan payments:thawani-check --connect
php artisan app:smoke-test
```
