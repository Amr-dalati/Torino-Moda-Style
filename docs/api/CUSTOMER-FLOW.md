# Customer Flow (End-to-End)

This document is a recommended request order for the mobile (Flutter) client.

## 1) Register or login
- Register: `POST /api/customer/register`
- Login: `POST /api/customer/login`

Store returned `token` and send it as Bearer auth for all customer routes.

## 2) Browse delivery options (public)
- `GET /api/delivery/regions`
- `GET /api/delivery/areas`

## 3) Browse catalog (authenticated)
- `GET /api/products`
- `GET /api/products/search?q=...`
- `GET /api/products/{id}`
- Optional barcode scan: `GET /api/products/barcode/{barcode}`

## 4) Cart
- `GET /api/customer/cart`
- Add item: `POST /api/customer/cart/items`
- Update item: `PUT /api/customer/cart/items/{id}`
- Remove item: `DELETE /api/customer/cart/items/{id}`

## 5) Addresses
- List: `GET /api/customer/addresses`
- Create: `POST /api/customer/addresses`
- Set default: `POST /api/customer/addresses/{id}/default`

## 6) Quote then checkout
- Quote: `POST /api/customer/checkout/quote`
- Checkout: `POST /api/customer/checkout`

## 7) Payment (MVP mock)
- In local/testing only, simulate payment success:
  - `POST /api/payments/mock/success`

## 8) View orders
- `GET /api/customer/orders`
- `GET /api/customer/orders/{id}`
- `GET /api/customer/orders/{id}/payment-status`

