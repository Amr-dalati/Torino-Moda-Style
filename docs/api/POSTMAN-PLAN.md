# Postman Collection Plan

This plan is designed to be accurate to the current routes and safe (no secrets committed).

## Environments
Create a Postman Environment with:
- `baseUrl` (e.g. `http://localhost`)
- `apiBase` = `{{baseUrl}}/api`
- `userToken` (Bearer token for User)
- `customerToken` (Bearer token for Customer)
- `customerAddressId`
- `productVariantId`
- `orderId`
- `merchantReference`

## Collection folders (recommended)
1. **Health**
   - `GET {{apiBase}}/health`
2. **Auth - User**
   - `POST {{apiBase}}/login`
   - `GET {{apiBase}}/me`
   - `POST {{apiBase}}/logout`
3. **Auth - Customer**
   - `POST {{apiBase}}/customer/register`
   - `POST {{apiBase}}/customer/login`
   - `GET {{apiBase}}/customer/me`
   - `POST {{apiBase}}/customer/logout`
4. **Delivery (Public)**
   - `GET {{apiBase}}/delivery/regions`
   - `GET {{apiBase}}/delivery/areas`
5. **Catalog**
   - `GET {{apiBase}}/products`
   - `GET {{apiBase}}/products/search?q=...`
   - `GET {{apiBase}}/products/{id}`
   - `GET {{apiBase}}/products/barcode/{barcode}`
6. **Stock**
   - `GET {{apiBase}}/stock`
   - `GET {{apiBase}}/stock/product/{product_id}`
   - `GET {{apiBase}}/stock/warehouse/{warehouse_id}`
7. **Cart (Customer)**
   - `GET {{apiBase}}/customer/cart`
   - `POST {{apiBase}}/customer/cart/items`
   - `PUT {{apiBase}}/customer/cart/items/{{cartItemId}}`
   - `DELETE {{apiBase}}/customer/cart/items/{{cartItemId}}`
   - `DELETE {{apiBase}}/customer/cart`
8. **Checkout / Orders / Payments (Customer)**
   - `POST {{apiBase}}/customer/checkout/quote`
   - `POST {{apiBase}}/customer/checkout`
   - `GET {{apiBase}}/customer/orders`
   - `GET {{apiBase}}/customer/orders/{{orderId}}`
   - `GET {{apiBase}}/customer/orders/{{orderId}}/payment-status`
9. **Mock Payment (local/testing only)**
   - `POST {{apiBase}}/payments/mock/success`

## Token handling
Use collection-level pre-request scripts (optional):
- For user routes: set header `Authorization: Bearer {{userToken}}`
- For customer routes: set header `Authorization: Bearer {{customerToken}}`

## Recommended execution order (MVP)
1. Health
2. Customer login/register
3. Delivery list
4. Products list → pick `productVariantId`
5. Add to cart
6. Create address → set `customerAddressId`
7. Quote → checkout → store `orderId` and `merchantReference`
8. Mock payment success (testing/local only)
9. Orders list/detail/payment-status

## Minimal tests (optional Postman scripts)
For most requests, assert envelope keys exist:
- `success`, `message`, `data`, `meta`, `errors`
And expected HTTP status codes (200/201/401/403/422/429/500).

