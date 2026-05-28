# API Overview (Torino Moda Style)

## Base
- **Base URL**: `<APP_URL>/api`
- **Auth**: Bearer tokens (Laravel Sanctum Personal Access Tokens)
- **Envelope**: all JSON responses use the same keys (see `docs/api/ERROR-ENVELOPE.md`)

## Modules
- **Auth (User/Admin)**: login, me, logout
- **Auth (Customer)**: register, login, me, logout
- **Delivery**: regions, areas (public)
- **Catalog**: products (authenticated)
- **Stock**: stock levels (authenticated)
- **Cart**: customer cart + mutations (customer only)
- **Checkout/Orders/Payments**: quote, checkout, orders, payment status (customer only)
- **Health**: health check (public)

## Rate limiting (high level)
Some endpoints are explicitly throttled (429 uses the standard envelope). Details in `docs/api/AUTH.md`, `docs/api/CART.md`, and `docs/api/CHECKOUT-ORDERS-PAYMENTS.md`.

## Environment-specific endpoints
- **Mock payment success** is **local/testing only**:
  - `POST /api/payments/mock/success`

