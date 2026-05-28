# Delivery

Delivery endpoints are **public** (no auth).

## List regions
- **GET** `/api/delivery/regions`
- **Auth**: none

Success example:

```json
{
  "success": true,
  "message": "OK",
  "data": [{ "id": 1, "code": "CAI", "name_en": "Cairo", "is_active": true }],
  "meta": null,
  "errors": null
}
```

## List areas
- **GET** `/api/delivery/areas`
- **Auth**: none
- **Filters**: supports filtering by region (see implementation)

Common errors: none (standard 500 envelope on unexpected errors).

