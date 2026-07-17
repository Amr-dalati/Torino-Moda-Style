# Phase 10E — Production Security and Admin Readiness

This document summarizes production security controls, admin stock workflows, and customer-facing stock behavior introduced in Phase 10E.

## API access-control matrix

| Route | Auth | Tokenable | Access |
| --- | --- | --- | --- |
| `POST /api/login` | Public (throttled) | — | Staff login |
| `POST /api/customer/register` | Public (throttled) | — | Customer registration |
| `POST /api/customer/login` | Public (throttled) | — | Customer login |
| `GET /api/health` | Public | — | Health probe |
| `GET /api/delivery/regions` | Public | — | Delivery regions |
| `GET /api/delivery/areas` | Public | — | Delivery areas |
| `GET /api/categories` | `auth:sanctum` | Customer or User | Catalog browse |
| `GET /api/brands` | `auth:sanctum` | Customer or User | Catalog browse |
| `GET /api/products` | `auth:sanctum` | Customer or User | Catalog browse |
| `GET /api/products/search` | `auth:sanctum` | Customer or User | Catalog browse |
| `GET /api/products/barcode/{barcode}` | `auth:sanctum` | Customer or User | Catalog browse |
| `GET /api/products/{id}` | `auth:sanctum` | Customer or User | Product detail |
| `GET /api/customer/*` | `auth:sanctum` | Customer only | Mobile customer APIs |
| `POST /api/logout` | `auth:sanctum` | User only | Staff logout |
| `GET /api/me` | `auth:sanctum` | User only | Staff profile |
| `GET /api/phoenix/health` | `auth:sanctum` | User only | Internal Phoenix diagnostics |
| `GET /api/stock` | `auth:sanctum` | User only | Warehouse stock index |
| `GET /api/stock/product/{id}` | `auth:sanctum` | User only | Stock by product |
| `GET /api/stock/warehouse/{id}` | `auth:sanctum` | User only | Stock by warehouse |
| `POST /api/payments/webhook/{provider}` | Public (throttled) | — | Signed provider webhooks |
| `POST /api/payments/mock/success` | `local.testing` only | — | Dev/test mock checkout helper |

### Standard envelopes

- Missing/invalid token: `401` with `{ "success": false, ... }`
- Wrong tokenable type: `403` with `{ "success": false, "message": "Forbidden." }`

## Staff-only APIs

- `/api/me` (staff)
- `/api/logout` (staff)
- `/api/phoenix/health`
- `/api/stock`
- `/api/stock/product/{id}`
- `/api/stock/warehouse/{id}`

## Customer-accessible catalog APIs

- `/api/categories`
- `/api/brands`
- `/api/products`
- `/api/products/search`
- `/api/products/barcode/{barcode}`
- `/api/products/{id}`

Customer tokens must **not** receive warehouse quantities, reserved quantities, warehouse IDs, or Phoenix diagnostics.

## Payment environment variables

| Variable | Required when | Notes |
| --- | --- | --- |
| `PAYMENT_PROVIDER` | Always | Use `thawani` in staging/production |
| `MOCK_PAYMENT_WEBHOOK_SECRET` | `mock` in local/testing | No unsafe default outside local/testing |
| `THAWANI_SECRET_KEY` | `thawani` | Never commit real values |
| `THAWANI_PUBLISHABLE_KEY` | `thawani` | Never commit real values |
| `THAWANI_WEBHOOK_SECRET` | `thawani` | Required for webhook verification |
| `THAWANI_SUCCESS_URL` | `thawani` | Backend return route |
| `THAWANI_CANCEL_URL` | `thawani` | Backend return route |
| `MOBILE_PAYMENT_SUCCESS_URL` | Mobile checkout | Deep-link return |
| `MOBILE_PAYMENT_CANCEL_URL` | Mobile checkout | Deep-link return |

### Secure webhook-secret rules

- No known default secret such as `mock-webhook-secret` is accepted in staging/production.
- Missing required secrets fail with a configuration error naming only the missing key, never the secret value.
- Secrets are not returned in API responses or written to sanitized logs.
- Mock payment helper endpoint remains available only in `local` and `testing`.

## Filament stock adjustment workflow

1. Open **Inventory → Stock Levels** in the admin panel.
2. Filter/search the row for product, warehouse, SKU, or barcode.
3. Use **Adjust stock** on a row.
4. Choose adjustment type:
   - **Increase** — add units to on-hand quantity
   - **Decrease** — remove units from on-hand quantity
   - **Set exact quantity** — set on-hand to an absolute value
5. Enter quantity and a required reason; optional reference/note.
6. The `StockAdjustmentService`:
   - locks the `stock_levels` row with `lockForUpdate`
   - validates final on-hand is never below `quantity_reserved`
   - updates only `quantity_on_hand`
   - writes a read-only audit row in `stock_adjustments`

Reserved quantities and paid-order stock commitment logic are unchanged.

## Stock adjustment audit schema

Table: `stock_adjustments`

| Column | Purpose |
| --- | --- |
| `stock_level_id` | Adjusted stock row |
| `product_variant_id` | Variant reference |
| `warehouse_id` | Warehouse reference |
| `user_id` | Admin who performed the adjustment |
| `adjustment_type` | `increase`, `decrease`, `set` |
| `quantity_before` | On-hand before change |
| `quantity_change` | Delta applied |
| `quantity_after` | On-hand after change |
| `reason` | Required business reason |
| `reference` | Optional note/reference |
| `metadata` | Optional JSON |
| `created_at` / `updated_at` | Audit timestamps |

Audit history is exposed in **Inventory → Stock Adjustments** as read-only.

## Low-stock threshold

```env
LOW_STOCK_THRESHOLD=5
```

Configured in `config/inventory.php`.

Filament status rules:

- **Out of stock**: available `<= 0`
- **Fully reserved**: on-hand `> 0` and available `<= 0`
- **Low stock**: available `> 0` and `<= LOW_STOCK_THRESHOLD`
- **In stock**: available `> LOW_STOCK_THRESHOLD`

## Order cancellation rules

| State | Admin cancel allowed | Stock effect |
| --- | --- | --- |
| `awaiting_payment` + pending payment | Yes (`cancelUnpaid`) | Releases reservation once |
| `payment_failed` / expired payment | Yes (`cancelUnpaid`) | Releases reservation if still held |
| `paid` / `processing` | No | Committed stock remains deducted |
| `shipped` / `delivered` | No | No cancellation |
| Already `cancelled` | No | Idempotent guard |

Paid-order cancellation requires a future refund/restock workflow. Simple admin cancel does not restock committed inventory.

## Customer-visible stock behavior

Product detail variant payloads include only:

```json
{
  "is_in_stock": true
}
```

Availability is computed as:

```text
sum(quantity_on_hand - quantity_reserved) across active warehouses > 0
```

Warehouse breakdown, reserved quantity, and exact sellable counts are not exposed to Flutter.

## Production deployment checklist

1. Set `APP_ENV=production` (or `staging`).
2. Set `PAYMENT_PROVIDER=thawani`.
3. Configure all Thawani secrets and return URLs.
4. Set a strong unique `THAWANI_WEBHOOK_SECRET`.
5. Do **not** rely on `mock-webhook-secret` in production.
6. Set `LOW_STOCK_THRESHOLD` for admin indicators.
7. Verify only active admin users can access `/admin`.
8. Confirm customer tokens cannot reach `/api/stock` or `/api/phoenix/health`.
9. Confirm product detail responses include `is_in_stock` only.
10. Run `php artisan test` before deploy.
11. Run Flutter `flutter analyze` and `flutter test` before mobile release.

## Manual production configuration steps

1. Copy `.env.example` values into the secure environment store.
2. Remove any mock payment defaults from production `.env`.
3. Configure Thawani UAT/production keys per environment.
4. Register webhook endpoint: `POST /api/payments/webhook/thawani`.
5. Keep mobile deep-link URLs aligned with app URL scheme configuration.
6. Train admins on stock adjustment reasons and audit review in Filament.
