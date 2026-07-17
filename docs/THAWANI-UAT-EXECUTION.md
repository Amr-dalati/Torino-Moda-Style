# Thawani UAT Manual Execution Guide

Controlled manual UAT for the full payment journey. **Do not automate real monetary transactions in CI.**

## Pre-checks

Run on the staging server before UAT:

```bash
php artisan app:production-check
php artisan payments:thawani-check
php artisan app:smoke-test --with-auth
```

Verify:

| Check | Command / URL | Expected |
|-------|---------------|----------|
| Readiness | `GET /api/readiness` | `ready` or documented `degraded` |
| Scheduler | `php artisan schedule:list` | `payments:expire-pending`, `ops:scheduler-heartbeat` |
| Heartbeat | `php artisan ops:scheduler-heartbeat` then readiness | Scheduler component fresh |
| Webhook route | See [WEBHOOK-REACHABILITY.md](./WEBHOOK-REACHABILITY.md) | POST `/api/payments/webhook/thawani` reachable externally |

Mobile staging build must use Thawani UAT host in `PAYMENT_ALLOWED_HOSTS`.

---

## Success scenario

1. **Login** as staging customer (credentials from `STAGING_*` env — not documented in git).
2. **Add in-stock variant** to cart (product `STAGING-UAT-PRODUCT` after seeder).
3. **Checkout** → select address → review → place order.
4. **Verify stock reservation** in Filament stock levels (reserved quantity increased).
5. **Open Thawani UAT checkout** from payment URL.
6. **Complete payment** in Thawani UAT.
7. **Webhook** — confirm payment webhook received (Filament payment status / logs — no secrets in notes).
8. **Backend state** — payment `paid`, order `paid`, stock committed.
9. **Cart** — cart status `checked_out` or empty for new session.
10. **Mobile deep link** — `torinomodastyle://payment/success?order_id=...` opens app.
11. **Flutter UI** — shows paid state after polling.

### Evidence to capture

- Order ID
- Order number
- Merchant reference
- Gateway session ID (from admin — not customer-facing URL with tokens)
- Payment status timestamps
- Stock: on-hand / reserved / committed before and after
- Screenshot of mobile paid state
- Sanitized log reference (no webhook signatures or keys)

---

## Cancel scenario

1. Create a **new order** (do not reuse paid order).
2. Open Thawani checkout.
3. **Cancel** payment in Thawani UI.
4. Confirm **cancel deep link** opens app: `torinomodastyle://payment/cancel`.
5. Confirm backend payment remains **unpaid** / maps to cancelled-failed per gateway mapping.
6. Confirm **stock reservation** still held until expiry or cancel processing.
7. After expiry or admin cancel of unpaid order — confirm reservation **released once**.

---

## Expiry scenario

1. Create order and **do not pay**.
2. Wait for `THAWANI_EXPIRY_MINUTES` or run scheduler manually:

```bash
php artisan payments:expire-pending
```

3. Confirm payment status **expired**.
4. Confirm stock reservation **released exactly once** (re-run command — idempotent).

---

## Duplicate webhook scenario

1. Complete a successful payment (Success scenario).
2. In controlled UAT only, **replay the same signed webhook** from Thawani dashboard or saved test payload.
3. Confirm payment remains **paid** (not double-paid).
4. Confirm stock **not committed twice**.

---

## Failure checklist

| Symptom | Check |
|---------|-------|
| Payment stuck pending | Webhook reachability, `THAWANI_WEBHOOK_SECRET`, scheduler |
| Deep link does not open | Android intent filter, iOS URL scheme, app installed |
| Invalid payment URL in app | `PAYMENT_ALLOWED_HOSTS` matches UAT checkout host |
| Stock not released | `payments:expire-pending` cron, order payment status |

## Related docs

- [THAWANI-UAT-CHECKLIST.md](./THAWANI-UAT-CHECKLIST.md)
- [UAT-TEST-PLAN.md](./UAT-TEST-PLAN.md)
- [WEBHOOK-REACHABILITY.md](./WEBHOOK-REACHABILITY.md)
