# Torino Moda Style — Sales API

Laravel 12 sales management API for **Torino Moda Style** (women's shoes & bags), integrated with **Phoenix ERP**.

## Stack

- Laravel 12, PHP 8.2+
- MySQL (production) / SQLite (local default)
- Laravel Sanctum (mobile API tokens)
- Filament 3 (admin panel at `/admin`)

## Requirements

- PHP 8.2+ with extensions: `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `curl`
- **Recommended:** enable `ext-zip` in `php.ini` (required for some Filament exports)
- Composer 2.x
- MySQL 8+ for production

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Default users (after seed)

| Email | Password | Role | Filament |
|-------|----------|------|----------|
| admin@torinomodastyle.com | password | admin | Yes |
| sales@torinomodastyle.com | password | sales | No |

## API

Base URL: `http://localhost:8000/api`

### Auth

```http
POST /api/login
{ "email": "sales@torinomodastyle.com", "password": "password", "device_name": "mobile" }

POST /api/logout   Authorization: Bearer {token}
GET  /api/me       Authorization: Bearer {token}
GET  /api/phoenix/health   (integration smoke test)
```

### Response format

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": null,
  "errors": null
}
```

## Phoenix (mock)

```env
PHOENIX_USE_MOCK=true
PHOENIX_API_BASE_URL=https://phoenix.example.com
PHOENIX_API_KEY=
PHOENIX_API_USERNAME=
PHOENIX_API_PASSWORD=
```

Fixtures: `database/fixtures/phoenix/`

## Documentation

- [MVP Architecture](docs/MVP-ARCHITECTURE.md)

## Phase status

- **Phase 0** — Foundation (auth, mock Phoenix, logging tables) ✅
- **Phase 1** — Products, stock, sync (planned)
- **Phase 2** — Customers & sales orders (planned)

## Tests

```bash
php artisan test
```
