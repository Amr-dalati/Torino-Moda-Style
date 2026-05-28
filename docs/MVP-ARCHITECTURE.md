# Torino Moda Style — MVP Phase 1 Architecture

## Scope (in)

| Module | MVP behavior |
|--------|----------------|
| Auth | Sanctum tokens; roles: `admin`, `sales` (no permission matrix) |
| Products | Synced from Phoenix mock; list, detail, search, **barcode lookup** |
| Variants | SKU = product + color + size; barcode per variant |
| Stock | Per variant + warehouse; block oversell on confirm |
| Customers | List, detail, create local (sync later) |
| Sales orders | draft → confirmed → synced_to_phoenix / cancelled |
| Phoenix | Mock layer only; swap via `PHOENIX_USE_MOCK=false` later |

## Out of scope (MVP)

Returns, invoices, reports, approvals, Spatie permissions, queues (optional single sync command first).

---

## Minimal tables (11)

```
users
categories, brands, colors, sizes
products, product_variants
warehouses, stock_levels
customers
sales_orders, sales_order_items
api_integration_logs, phoenix_sync_logs
```

**Deferred:** `invoices`, `returns`, `roles`, `permissions`, `order_stock_reservations` (use `quantity_reserved` on `stock_levels`).

### Key fields (summary)

| Table | Important columns |
|-------|-------------------|
| users | role (`admin`/`sales`), phone, is_active, warehouse_id (nullable) |
| products | phoenix_id, product_code, barcode, name_ar, name_en, category_id, brand_id, sale_price, status |
| product_variants | product_id, color_id, size_id, sku, barcode, sale_price |
| stock_levels | variant_id, warehouse_id, quantity_on_hand, quantity_reserved |
| sales_orders | order_number, customer_id, warehouse_id, status, totals, phoenix_order_id, sync_status |
| sales_order_items | order_id, variant_id, qty, unit_price, line_total |

**Order statuses:** `draft` | `confirmed` | `synced_to_phoenix` | `cancelled`  
(No `pending` in MVP — rep confirms directly.)

---

## Minimal API

| Method | Path |
|--------|------|
| POST | `/api/login` |
| POST | `/api/logout` |
| GET | `/api/me` |
| GET | `/api/products` |
| GET | `/api/products/search?q=` |
| GET | `/api/products/barcode/{barcode}` |
| GET | `/api/products/{id}` |
| GET | `/api/stock` |
| GET | `/api/stock/product/{productId}` |
| GET | `/api/stock/warehouse/{warehouseId}` |
| GET | `/api/customers` |
| GET | `/api/customers/{id}` |
| POST | `/api/customers` |
| GET | `/api/sales-orders` |
| POST | `/api/sales-orders` |
| GET | `/api/sales-orders/{id}` |
| POST | `/api/sales-orders/{id}/confirm` |
| POST | `/api/sales-orders/{id}/cancel` |

---

## Service structure (flat)

```
app/Services/
  Auth/AuthService.php
  Products/ProductQueryService.php
  Stock/StockQueryService.php
  Customers/CustomerService.php
  SalesOrders/CreateSalesOrderService.php
  SalesOrders/ConfirmSalesOrderService.php
  Sync/SyncFromPhoenixService.php   # artisan phoenix:sync

app/Integrations/Phoenix/
  Contracts/*.php
  PhoenixClient.php
  Mock/*.php
  Mappers/*.php (Phase 1+)
```

**Flow:** `Controller → App Service → Phoenix Interface (sync) / Eloquent (read)`

---

## Phoenix MVP strategy

- `PHOENIX_USE_MOCK=true` → mock services read JSON fixtures.
- `phoenix:sync` command pulls products + stock + customers into MySQL (no queue in MVP).
- On confirm order: synchronous mock POST; log to `phoenix_sync_logs` + `api_integration_logs`.
- Failed sync: order stays `confirmed`, `sync_status=failed`; retry via `phoenix:retry-order {id}`.

---

## Implementation phases

| Phase | Deliverable | Days (est.) |
|-------|-------------|-------------|
| **0** ✅ | Laravel, Sanctum, Filament, auth API, mock Phoenix shell, logs tables | 1–2 |
| **1** | Migrations + models + `phoenix:sync` + product/stock APIs + barcode | 3–4 |
| **2** | Customers + sales orders + confirm/cancel + mock push | 3–4 |
| **3** | Filament: users, sync button, failed orders; polish + tests | 2 |

---

## Fast sales workflow (mobile)

1. Login → token stored.
2. Scan barcode → `GET /products/barcode/{code}` → variant + stock.
3. Pick customer → create draft order.
4. Add lines → confirm → Phoenix sync (background or sync in MVP).
5. Show order number + sync status.
