# Authentication

## Bearer token usage
Send the token in:

```
Authorization: Bearer <token>
Accept: application/json
```

## Tokenable model separation
This backend enforces two tokenable types:
- **User/Admin token**: `App\Models\User`
- **Customer token**: `App\Models\Customer`

Some routes require a specific tokenable type and return **403** when a different tokenable is used.

## User/Admin auth endpoints

### Login (throttled)
- **POST** `/api/login`
- **Auth**: none
- **Rate limit**: strict (per-IP)

Request:

```json
{ "email": "admin@example.com", "password": "password", "device_name": "mobile" }
```

Success:

```json
{
  "success": true,
  "message": "Logged in successfully",
  "data": { "token": "...", "token_type": "Bearer", "user": {} },
  "meta": null,
  "errors": null
}
```

### Me
- **GET** `/api/me`
- **Auth**: `auth:sanctum` + **User tokenable**

### Logout
- **POST** `/api/logout`
- **Auth**: `auth:sanctum` + **User tokenable**

## Customer auth endpoints

### Register (throttled)
- **POST** `/api/customer/register`
- **Auth**: none
- **Rate limit**: strict (per-IP)

### Login (throttled)
- **POST** `/api/customer/login`
- **Auth**: none
- **Rate limit**: strict (per-IP)

### Me
- **GET** `/api/customer/me`
- **Auth**: `auth:sanctum` + **Customer tokenable**

### Logout
- **POST** `/api/customer/logout`
- **Auth**: `auth:sanctum` + **Customer tokenable**

## Shared authenticated routes (no tokenable restriction)
These require `auth:sanctum` but allow either **User** or **Customer** tokens:
- `GET /api/products`
- `GET /api/products/search`
- `GET /api/products/barcode/{barcode}`
- `GET /api/products/{id}`
- `GET /api/stock`
- `GET /api/stock/product/{product_id}`
- `GET /api/stock/warehouse/{warehouse_id}`

