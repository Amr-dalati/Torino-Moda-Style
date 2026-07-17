# Catalog & Stock

These endpoints require `auth:sanctum` (either **User** or **Customer** token works).

See also: [CATALOG.md](./CATALOG.md) (categories, brands, filters, images) and [CATALOG-IMAGES.md](./CATALOG-IMAGES.md).

## Categories list
- **GET** `/api/categories`
- **Auth**: Bearer token (User or Customer)

## Brands list
- **GET** `/api/brands`
- **Auth**: Bearer token (User or Customer)

## Products list (paginated)
- **GET** `/api/products`
- **Auth**: Bearer token (User or Customer)
- **Query**:
  - `per_page` (1..100)
  - `page`
  - `category_id` (optional)
  - `brand_id` (optional)
  - `q` (optional search on list)
  - `sort` — `newest`, `price_asc`, `price_desc`, `name_asc`

Success:
- Returns paginated envelope (`meta.current_page`, `meta.per_page`, `meta.total`, `meta.last_page`)
- Each product includes `primary_image_url` (null if none) and `images` (empty on list)

## Products search (paginated)
- **GET** `/api/products/search`
- **Auth**: Bearer token (User or Customer)
- **Query**:
  - `q` (required)
  - `per_page` (optional)
  - `category_id`, `brand_id`, `sort` (optional)

Common errors:
- **422** if `q` missing/invalid

## Products barcode lookup
- **GET** `/api/products/barcode/{barcode}`
- **Auth**: Bearer token (User or Customer)

Common errors:
- **422** invalid barcode length/format
- **404** product not found

## Product detail
- **GET** `/api/products/{id}`
- **Auth**: Bearer token (User or Customer)
- Includes `primary_image_url`, `images[]`, descriptions, visibility fields

## Stock list (paginated)
- **GET** `/api/stock`
- **Auth**: Bearer token (User or Customer)

## Stock by product
- **GET** `/api/stock/product/{product_id}`
- **Auth**: Bearer token (User or Customer)

## Stock by warehouse
- **GET** `/api/stock/warehouse/{warehouse_id}`
- **Auth**: Bearer token (User or Customer)

