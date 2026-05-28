# Checkout, Orders, Payments (Customer)

All endpoints in this document require:
- `auth:sanctum`
- tokenable type: **Customer**

Checkout endpoints are rate-limited (429 uses standard envelope).

## Checkout quote (throttled)
- **POST** `/api/customer/checkout/quote`

Request:

```json
{ "address_id": 10 }
```

Success `data`:
- `subtotal` (string, 2 decimals)
- `delivery_fee` (string, 2 decimals)
- `discount_total` (string, 2 decimals) currently always `"0.00"`
- `total` (string, 2 decimals)

Common errors:
- **422** cart empty, inactive delivery area, missing delivery area
- **404** address not found or not owned by customer (scoped lookup)

## Checkout (throttled, idempotent per cart)
- **POST** `/api/customer/checkout`

Behavior:
- Creates an `order` + `payment (pending)` from the active cart.
- **Idempotent**: repeated calls for the same active cart return the same order/payment.

Success (201) `data`:
- `order`: order resource
- `payment`: payment resource (amount is string 2 decimals)

## Orders list (paginated)
- **GET** `/api/customer/orders`

## Order detail
- **GET** `/api/customer/orders/{id}`

## Order payment status
- **GET** `/api/customer/orders/{id}/payment-status`

## Mock payment success (local/testing only, throttled)
- **POST** `/api/payments/mock/success`
- **Env**: local/testing only (blocked elsewhere)

Request:

```json
{ "merchant_reference": "mr_TMS-2026-000001" }
```

Behavior:
- Idempotent: repeated calls keep state consistent.
- Only transitions **pending → paid**; otherwise returns **422**.

