# Error & Response Envelope

All API JSON responses follow the same top-level keys:

## Success envelope

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": null,
  "errors": null
}
```

## Pagination envelope
Paginated endpoints return `meta`:

```json
{
  "success": true,
  "message": "OK",
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 123,
    "last_page": 7
  },
  "errors": null
}
```

## 422 Validation error

```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "meta": null,
  "errors": {
    "field_name": ["The field_name field is required."]
  }
}
```

## 401 Unauthenticated

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "data": null,
  "meta": null,
  "errors": null
}
```

## 403 Forbidden
Used for authorization failures (including tokenable-type mismatch).

```json
{
  "success": false,
  "message": "Forbidden.",
  "data": null,
  "meta": null,
  "errors": null
}
```

## 404 Not found

```json
{
  "success": false,
  "message": "Not found.",
  "data": null,
  "meta": null,
  "errors": null
}
```

## 429 Too many requests

```json
{
  "success": false,
  "message": "Too many requests.",
  "data": null,
  "meta": null,
  "errors": null
}
```

## 500 Server error
Safe response without SQL/stack traces.

```json
{
  "success": false,
  "message": "Server error.",
  "data": null,
  "meta": null,
  "errors": null
}
```

