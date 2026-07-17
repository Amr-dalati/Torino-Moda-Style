# Torino Moda Style — Sales API

Laravel 12 sales management API for **Torino Moda Style** (women's shoes & bags), integrated with **Phoenix ERP**.

## Stack

- Laravel 12, PHP 8.2+
- MySQL (production) / SQLite (local default)
- Laravel Sanctum (mobile API tokens)
- Filament 3 (admin panel at `/admin`)

## Requirements

- PHP 8.2+ with extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `curl`
- **Recommended:** enable `ext-zip` in `php.ini` (required for some Filament exports)
- Composer 2.x
- MySQL 8+ for production

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link   # required for admin product/category/brand image uploads
php artisan serve
```

### Default users (after seed)

| Email | Password | Role | Filament |
|-------|----------|------|----------|
| admin@torinomodastyle.com | password | admin | Yes |
| sales@torinomodastyle.com | password | sales | No |

## API

Base URL: `http://localhost:8000/api`

### Auth

```http
POST /api/login
{ "email": "sales@torinomodastyle.com", "password": "password", "device_name": "mobile" }

POST /api/logout   Authorization: Bearer {token}
GET  /api/me       Authorization: Bearer {token}
GET  /api/phoenix/health   (integration smoke test)
```

### Response format

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": null,
  "errors": null
}
```

## Phoenix (mock)

```env
PHOENIX_USE_MOCK=true
PHOENIX_API_BASE_URL=https://phoenix.example.com
PHOENIX_API_KEY=
PHOENIX_API_USERNAME=
PHOENIX_API_PASSWORD=
```

Fixtures: `database/fixtures/phoenix/`

## Documentation

- [MVP Architecture](docs/MVP-ARCHITECTURE.md)
- [Catalog API](docs/api/CATALOG.md) — categories, brands, product filters & images
- [Catalog images (HTTP caching)](docs/api/CATALOG-IMAGES.md)

## Phase status

- **Phase 0** — Foundation (auth, mock Phoenix, logging tables) ✅
- **Phase 1** — Products, stock, sync (planned)
- **Phase 2** — Customers & sales orders (planned)

## Tests

```bash
php artisan test
```

## Stock lifecycle (Phase 10A)

Sellable stock per variant = sum(`quantity_on_hand - quantity_reserved`) across warehouses.

1. **Cart** — validates against sellable stock only (no reservation yet).
2. **Checkout** — creates order items, then `StockReservationService::reserveForOrder()` increments `quantity_reserved` and stores JSON `stock_allocations` on the order.
3. **Payment paid** — `commitForPaidOrder()` decrements both `quantity_on_hand` and `quantity_reserved` (idempotent via `stock_committed_at`).
4. **Payment failed / expired** — `releaseForOrder()` decrements `quantity_reserved` only (idempotent via `stock_released_at`).
5. **Admin cancel after payment** — order status only; stock is **not** restocked automatically (refund/restock is a separate flow).

Mock payment: `POST /api/payments/mock/success` (local/testing only).

## Payments & webhooks (Phase 10B)

```env
PAYMENT_PROVIDER=mock
MOCK_PAYMENT_WEBHOOK_SECRET=mock-webhook-secret
```

- **Provider binding** — `PAYMENT_PROVIDER` selects the gateway via `PaymentGatewayResolver` (default: `mock`). Unsupported values fail at resolve time.
- **Webhook route** — `POST /api/payments/webhook/{provider}` (no Sanctum; signature required). Every attempt is stored in `payment_webhooks` with redacted payload.
- **Mock webhook (local/testing)** — JSON body with `event_id`, `merchant_reference`, `status` (`paid`, `failed`, `expired`, `cancelled`). Sign with HMAC-SHA256 using `MOCK_PAYMENT_WEBHOOK_SECRET`; send digest in header `X-Mock-Signature`.
- **Pending expiry** — `php artisan payments:expire-pending` marks overdue pending payments expired and releases stock. Scheduled every 5 minutes via `routes/console.php`. Production requires cron: `* * * * * php artisan schedule:run`.
- **Production** — do not use `PAYMENT_PROVIDER=mock` with the default `MOCK_PAYMENT_WEBHOOK_SECRET`; use a real provider and a strong webhook secret.
- **Real provider (later)** — implement `PaymentGatewayInterface` (`createCheckout`, `verifyWebhookSignature`, `parseWebhookEvent`), register in `PaymentGatewayResolver`, set `PAYMENT_PROVIDER`. Flutter will need checkout URL open + return/deep link handling after provider choice.

## Thawani (Phase 10C)

Set `PAYMENT_PROVIDER=thawani` and configure:

```env
THAWANI_SECRET_KEY=
THAWANI_PUBLISHABLE_KEY=
THAWANI_WEBHOOK_SECRET=
THAWANI_BASE_URL=https://uatcheckout.thawani.om/api/v1
THAWANI_CHECKOUT_BASE_URL=https://uatcheckout.thawani.om
THAWANI_SUCCESS_URL="${APP_URL}/payments/thawani/success"
THAWANI_CANCEL_URL="${APP_URL}/payments/thawani/cancel"
THAWANI_EXPIRY_MINUTES=30
```

Production URLs: `https://checkout.thawani.om/api/v1` and `https://checkout.thawani.om`.

- **Webhook URL** — register in Thawani merchant portal: `{APP_URL}/api/payments/webhook/thawani` (must be publicly reachable over HTTPS for UAT/production).
- **Success / cancel URLs** — minimal placeholder pages at `GET /payments/thawani/success` and `GET /payments/thawani/cancel` (informational only; do not trust redirect for payment state).
- **Checkout** — `POST /api/customer/checkout` returns `payment.checkout_url` (hosted Thawani page). Amounts sent in OMR baisa (×1000). Session product total must match `payment.amount` exactly; order line items are sent plus a separate **Delivery fee** line when applicable. Orders with discounts use a single product line for the final payable amount.
- **Scheduler** — `* * * * * php artisan schedule:run` (runs `payments:expire-pending` every 5 minutes).
- **Flutter (later)** — open `payment.checkout_url` in WebView or external browser; poll `GET /api/customer/orders/{id}/payment-status`; do not trust redirect alone. Never embed `THAWANI_SECRET_KEY` or `THAWANI_WEBHOOK_SECRET` in the mobile app.
