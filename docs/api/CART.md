# Cart (Customer)

All cart endpoints require:
- `auth:sanctum`
- tokenable type: **Customer**

Cart mutation endpoints are rate-limited (429 uses standard envelope).

## Get active cart
- **GET** `/api/customer/cart`

## Add item (throttled)
- **POST** `/api/customer/cart/items`

Request:

```json
{ "product_variant_id": 123, "quantity": 2 }
```

Common errors:
- **422** invalid input / above stock
- **401** unauthenticated
- **403** wrong tokenable type

## Update item quantity (throttled)
- **PUT** `/api/customer/cart/items/{id}`

## Remove item (throttled)
- **DELETE** `/api/customer/cart/items/{id}`

## Clear cart (throttled)
- **DELETE** `/api/customer/cart`

Money fields:
- `subtotal`, `unit_price_snapshot`, `line_total` are returned as **strings with 2 decimals**.

