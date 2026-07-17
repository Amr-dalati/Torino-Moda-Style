# Catalog product images (HTTP caching)

Product images are **static files** on Laravel's `public` disk — not stored in the database or Redis as binary data. The database stores only `path`, `disk`, and metadata (`alt_text`, `sort_order`, `is_primary`).

## Upload optimization (admin)

When a product image is uploaded from Filament:

1. Accepted types: JPEG, PNG, WebP.
2. The file is stored on the `public` disk under `products/{product_id}/`.
3. `ProductImageOptimizer` (Intervention Image + GD) then:
   - Resizes images wider than **1600px** (aspect ratio preserved).
   - Converts to **WebP** at quality **~80%**.
   - Saves as `products/{product_id}/{uuid}.webp`.
   - Deletes the original upload file when conversion succeeds.

If GD is unavailable or conversion fails, the original uploaded file path is kept (JPEG/PNG/WebP).

Existing images uploaded before optimization continue to work unchanged.

## Storage layout

- Disk: `public` (see `config/filesystems.php`)
- Path pattern: `products/{product_id}/{uuid}.webp` (optimized) or legacy `{uuid}.{ext}`
- Public URL: `/storage/products/{product_id}/...` after `php artisan storage:link`

## Serving

Serve images through your web server or CDN directly from `storage/app/public`. Do not route image bytes through Redis or API JSON cache.

## Cache-Control recommendations

When filenames are **unique or versioned** (UUID on upload/replace):

```
Cache-Control: public, max-age=2592000, immutable
```

Use `immutable` only when the URL always maps to the same bytes. Admin uploads use UUID filenames so replaced images get new URLs.

If an image can be replaced **at the same path**, do **not** use `immutable`; use a shorter `max-age` or cache-busting query strings.

## Flutter

Use `cached_network_image` (or equivalent) with the `primary_image_url` / `images[].url` fields from the products API. Prefer the versioned URL from the API rather than constructing paths client-side.
