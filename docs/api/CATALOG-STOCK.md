# Catalog & Stock

These endpoints require `auth:sanctum` (either **User** or **Customer** token works).

## Products list (paginated)
- **GET** `/api/products`
- **Auth**: Bearer token (User or Customer)
- **Query**:
  - `per_page` (1..100)

Success:
- Returns paginated envelope (`meta.current_page`, `meta.per_page`, `meta.total`, `meta.last_page`)

## Products search (paginated)
- **GET** `/api/products/search`
- **Auth**: Bearer token (User or Customer)
- **Query**:
  - `q` (required)
  - `per_page` (optional)

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

## Stock list (paginated)
- **GET** `/api/stock`
- **Auth**: Bearer token (User or Customer)

## Stock by product
- **GET** `/api/stock/product/{product_id}`
- **Auth**: Bearer token (User or Customer)

## Stock by warehouse
- **GET** `/api/stock/warehouse/{warehouse_id}`
- **Auth**: Bearer token (User or Customer)

