# Thawani UAT Checklist

Use this checklist before controlled Thawani UAT testing. All values are examples — configure real UAT credentials outside version control.

## 1. Backend configuration

- [ ] `APP_ENV=staging` (or dedicated UAT environment)
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` is public HTTPS (e.g. `https://api.example.com`)
- [ ] `PAYMENT_PROVIDER=thawani`
- [ ] `THAWANI_BASE_URL` points to UAT API (e.g. `https://uatcheckout.thawani.om/api/v1`)
- [ ] `THAWANI_CHECKOUT_BASE_URL` points to UAT checkout host
- [ ] `THAWANI_PUBLISHABLE_KEY` set (from Thawani UAT dashboard)
- [ ] `THAWANI_SECRET_KEY` set (from Thawani UAT dashboard)
- [ ] `THAWANI_WEBHOOK_SECRET` set (strong random value)
- [ ] `THAWANI_SUCCESS_URL=https://{APP_URL}/payments/thawani/success`
- [ ] `THAWANI_CANCEL_URL=https://{APP_URL}/payments/thawani/cancel`
- [ ] `MOBILE_PAYMENT_SUCCESS_URL=torinomodastyle://payment/success`
- [ ] `MOBILE_PAYMENT_CANCEL_URL=torinomodastyle://payment/cancel`

Run validation:

```bash
php artisan app:production-check
php artisan payments:thawani-check
```

Optional connectivity (only when ready):

```bash
php artisan payments:thawani-check --connect
```

## 2. Thawani dashboard (manual)

- [ ] UAT merchant account active
- [ ] Webhook URL registered: `https://api.example.com/api/payments/webhook/thawani` (confirm exact route in `routes/api.php`)
- [ ] Webhook secret matches `THAWANI_WEBHOOK_SECRET`
- [ ] Success/cancel redirect URLs match backend routes
- [ ] Publishable and secret keys copied to environment (not committed)

## 3. Mobile app build

```bash
flutter build apk --release \
  --dart-define=APP_ENV=staging \
  --dart-define=API_BASE_URL=https://api.example.com/api \
  --dart-define=PAYMENT_ALLOWED_HOSTS=uatcheckout.thawani.om \
  --dart-define=LEGAL_BASE_URL=https://api.example.com/legal
```

- [ ] `PAYMENT_ALLOWED_HOSTS` includes UAT checkout host only
- [ ] Deep-link scheme `torinomodastyle` opens app on payment return
- [ ] Legal pages load from backend (`/legal/*`)

## 4. Infrastructure

- [ ] HTTPS certificate valid on API host
- [ ] Cron running: `* * * * * php /path/to/artisan schedule:run`
- [ ] `payments:expire-pending` scheduled (every 5 minutes)
- [ ] `ops:scheduler-heartbeat` scheduled (every minute)
- [ ] `GET /api/health` returns 200
- [ ] `GET /api/readiness` returns `ready` or documents known `degraded` components
- [ ] Storage symlink: `php artisan storage:link`

## 5. UAT test accounts

Document separately (not in git):

| Role | Identifier | Notes |
|------|------------|-------|
| Customer test account | Phone / password | For mobile login UAT |
| Admin staff account | Email / password | Filament order management |

## 6. UAT payment flow (manual)

1. Customer logs in on mobile
2. Adds in-stock variant to cart
3. Completes checkout → order created, stock reserved
4. Opens Thawani UAT checkout in browser
5. **Success path:** pay → webhook → deep link → polling confirms paid
6. **Cancel path:** cancel → deep link → order remains unpaid
7. **Expiry path:** wait past expiry → scheduler marks expired, stock released
8. **Duplicate webhook:** replay webhook → idempotent, no double charge

## 7. Incident handling

| Incident | Action |
|----------|--------|
| Webhook outage | Customer may return via deep link; polling reconciles status |
| Scheduler outage | Pending payments may not expire on time; run `payments:expire-pending` manually |
| Thawani outage | Do not create orders without checkout; communicate to testers |
| Wrong callback URL | Fix env + Thawani dashboard; re-test with new order |

## 8. Production cutover differences

| Item | UAT | Production |
|------|-----|------------|
| `THAWANI_BASE_URL` | `uatcheckout.thawani.om` | Production Thawani URL |
| `PAYMENT_ALLOWED_HOSTS` | UAT host | Production host |
| `APP_ENV` | `staging` | `prod` (mobile) / `production` (backend) |
| Webhook URL | UAT API host | Production API host |

Re-run `app:production-check` and `payments:thawani-check` after every environment change.
