# Catalog API (categories, brands, products)

All endpoints require `auth:sanctum` (User or Customer token).

## Categories

- **GET** `/api/categories`
- Cached ~15 minutes (version-bumped invalidation on catalog changes)

Success `data[]` item:

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | |
| `code` | string | Phoenix-owned when `phoenix_id` set |
| `name_ar` | string | |
| `name_en` | string | |
| `image_url` | string\|null | Public disk URL when admin uploaded |
| `is_active` | bool | Only active categories returned |

## Brands

- **GET** `/api/brands`
- Cached ~15 minutes

Success `data[]` item:

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | |
| `code` | string | |
| `name` | string | Legacy field |
| `name_ar` | string\|null | |
| `name_en` | string | |
| `logo_url` | string\|null | Public disk URL when admin uploaded |
| `is_active` | bool | Only active brands returned |

## Products list

- **GET** `/api/products`
- Query: `per_page` (1–100), `page`, `category_id`, `brand_id`, `q`, `sort`
- Sort values: `newest` (default), `price_asc`, `price_desc`, `name_asc`
- Returns only `is_active` + `is_visible` products
- Paginated envelope unchanged (`meta.current_page`, etc.)

Examples:

```
GET /api/products?category_id=1
GET /api/products?brand_id=2
GET /api/products?category_id=1&brand_id=2&sort=price_asc
GET /api/products?q=heel&sort=newest&per_page=20
```

## Product detail

- **GET** `/api/products/{id}`

### Product fields (additions)

| Field | Type | Notes |
|-------|------|-------|
| `description_ar` | string\|null | |
| `description_en` | string\|null | |
| `is_visible` | bool | |
| `is_featured` | bool | |
| `primary_image_url` | string\|null | `null` when no images |
| `images` | array | Empty when not loaded on list; populated on detail |

### `images[]` item

| Field | Type |
|-------|------|
| `id` | int |
| `url` | string |
| `alt_text` | string\|null |
| `sort_order` | int |
| `is_primary` | bool |

Nested `category` may include `image_url`; nested `brand` may include `logo_url`.

## Caching

- Redis/file cache stores **JSON API responses only** (not image binaries)
- TTL: 900 seconds; invalidated when products, categories, brands, or product images change
- Manual flush: bump via `CatalogCache::flush()` or `php artisan cache:clear`

## Admin / Phoenix ownership

- Products: `source` = `phoenix` (synced) or `manual` (Filament-created)
- Phoenix sync skips `manual` products and does not overwrite admin presentation fields on Phoenix rows (`is_visible`, `is_featured`, `sort_order`, descriptions, images)
- See [CATALOG-IMAGES.md](./CATALOG-IMAGES.md) for image storage and HTTP cache headers
