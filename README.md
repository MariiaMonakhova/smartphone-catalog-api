# Smartphone Product Catalog

[![Lint, Tests & Migrations](https://github.com/MariiaMonakhova/smartphone-catalog-api/actions/workflows/ci.yml/badge.svg)](https://github.com/MariiaMonakhova/smartphone-catalog-api/actions/workflows/ci.yml)

A RESTful backend (PHP 8.4+ / Laravel 13) that manages a local catalog of
smartphones. It seeds itself from the [DummyJSON](https://dummyjson.com) public
API via an Artisan command, exposes CRUD endpoints for products, and can return
prices converted to **UAH** and **EUR** using live
[National Bank of Ukraine (NBU)](https://bank.gov.ua) exchange rates.

---

## Table of contents

- [What it does](#what-it-does)
- [Tech stack](#tech-stack)
- [Quick start](#quick-start)
- [API reference](#api-reference)
- [Multi-currency support](#multi-currency-support)
- [Seeding from DummyJSON](#seeding-from-dummyjson)
- [Database schema](#database-schema)
- [How the application is organised](#how-the-application-is-organised)
- [Testing](#testing)
- [Code style](#code-style)
- [How AI was used](#how-ai-was-used)

---

## What it does

- **Seeds** smartphones from DummyJSON into a local database (idempotently).
- Provides full **CRUD** over products via a JSON REST API.
- Stores prices in **USD** (DummyJSON's currency) and converts them to **UAH**
  or **EUR** on request, using NBU rates that are **cached once per day**.
- Validates all input and returns meaningful HTTP status codes
  (`201`, `204`, `404`, `422`, `503`).
- Is covered by an automated **Pest** test suite that runs with a single command.

## Tech stack

| Concern       | Choice                                           |
| ------------- | ------------------------------------------------ |
| Language      | PHP 8.5 (≥ 8.4 required)                         |
| Framework     | Laravel 13                                       |
| ORM           | Eloquent                                         |
| App database  | MySQL 8.4 (via Docker Compose)                   |
| Test database | SQLite in-memory (zero external dependencies)    |
| Tests         | Pest 4                                           |
| HTTP client   | Laravel HTTP client (`Illuminate\Http\Client`)   |
| API docs      | Scramble (auto-generated OpenAPI at `/docs/api`) |

---

## Quick start

**Prerequisites:** PHP ≥ 8.4, Composer, Docker (for MySQL).

```bash
# 1. Install PHP dependencies
composer install

# 2. Create your env file and app key
cp .env.example .env
php artisan key:generate

# 3. Start MySQL (credentials already match .env)
docker compose up -d

# 4. Create the schema
php artisan migrate

# 5. Import smartphones from DummyJSON
php artisan products:seed

# 6. Serve the API
php artisan serve
```

The API is now available at `http://127.0.0.1:8000/api`.

> **Don't want Docker?** Set `DB_CONNECTION=sqlite` in `.env`, run
> `touch database/database.sqlite`, then `php artisan migrate`. Everything else
> works identically — Eloquent abstracts the database.

---

## API reference

> **Interactive docs:** once the server is running, open
> **<http://127.0.0.1:8000/docs/api>** for a full OpenAPI reference with a
> live **Try It** console, or grab the raw spec at `/docs/api.json`.

Base path: `/api`

| Method   | Endpoint         | Description                                                           |
| -------- | ---------------- | --------------------------------------------------------------------- |
| `GET`    | `/products`      | List products. Supports `?page=`, `?limit=`, `?brand=`, `?currency=`. |
| `GET`    | `/products/{id}` | Get one product by local ID. Supports `?currency=`.                   |
| `POST`   | `/products`      | Create a product from a JSON body.                                    |
| `PATCH`  | `/products/{id}` | Partial update — only fields present in the body are changed.         |
| `DELETE` | `/products/{id}` | Delete a product.                                                     |

### Query parameters (list)

| Param      | Default | Notes                                          |
| ---------- | ------- | ---------------------------------------------- |
| `page`     | `1`     | Page number.                                   |
| `limit`    | `20`    | Items per page (1–100).                        |
| `brand`    | —       | Exact-match brand filter.                      |
| `currency` | `USD`   | One of `USD`, `EUR`, `UAH` (case-insensitive). |

### Example requests

```bash
# List, 5 per page, Apple only, prices in UAH
curl "http://127.0.0.1:8000/api/products?limit=5&brand=Apple&currency=UAH"

# One product with prices in EUR
curl "http://127.0.0.1:8000/api/products/1?currency=EUR"

# Create
curl -X POST http://127.0.0.1:8000/api/products \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"title":"Pixel 42","price":799.99,"brand":"Google","stock":12}'

# Partial update (only the price changes)
curl -X PATCH http://127.0.0.1:8000/api/products/1 \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"price":149.00}'

# Delete
curl -X DELETE http://127.0.0.1:8000/api/products/1 -H "Accept: application/json"
```

### Example response (`GET /api/products/1?currency=EUR`)

Every product carries `currency`, the converted `price`, and the untouched
`price_usd`, so a consumer always knows exactly what a price means:

```json
{
  "data": {
    "id": 1,
    "external_id": 121,
    "title": "iPhone 5s",
    "category": "smartphones",
    "currency": "EUR",
    "price": 174.96,
    "price_usd": 199.99,
    "brand": "Apple",
    "stock": 25,
    "dimensions": { "width": 5.29, "height": 18.38, "depth": 17.72 },
    "tags": ["smartphones", "apple"],
    "...": "..."
  }
}
```

### Status codes

| Code  | When                                                         |
| ----- | ------------------------------------------------------------ |
| `200` | Successful read / update.                                    |
| `201` | Product created.                                             |
| `204` | Product deleted.                                             |
| `404` | Product not found.                                           |
| `422` | Validation failed (bad body, or unsupported `?currency=`).   |
| `503` | Currency conversion requested but NBU rates are unavailable. |

---

## Multi-currency support

Prices are **stored in USD** (the currency DummyJSON returns). Conversion happens
at read time.

### How conversion works

The NBU API returns the **UAH value of one unit** of a currency (e.g.
`USD → 44.4950` means 1 USD = 44.4950 UAH). From that:

| Target | Formula                         | Rationale                                         |
| ------ | ------------------------------- | ------------------------------------------------- |
| `USD`  | `price` (unchanged)             | Stored currency; the default. No NBU call.        |
| `UAH`  | `price × rate(USD)`             | Direct NBU rate.                                  |
| `EUR`  | `price × rate(USD) / rate(EUR)` | Cross-rate via UAH — NBU only quotes against UAH. |

Implemented in [`app/Services/CurrencyConverter.php`](app/Services/CurrencyConverter.php).

### Caching strategy

Implemented in [`app/Services/NbuExchangeRateService.php`](app/Services/NbuExchangeRateService.php):

- Each currency's rate is cached under `nbu:rate:{CC}` **until the end of the
  current day** (`Cache::remember(..., now()->endOfDay(), ...)`). So NBU is hit
  **at most once per currency per day**, no matter how many API requests arrive.
  Rates change once daily, so nothing fresher is needed.
- On every successful fetch we _also_ store a non-expiring copy under
  `nbu:rate:{CC}:last_good`.

### When the NBU API is unavailable

Handled gracefully, in this order of preference:

1. **Fresh daily cache** — served if present (no network call at all).
2. **Stale "last known good" rate** — if the daily cache is cold _and_ NBU is
   unreachable, the last successfully fetched rate is used, so the API keeps
   working through a transient outage.
3. **`503 Service Unavailable`** — only if there is _no_ usable rate at all. The
   response makes clear the failure is upstream and transient, not a bad request.

`USD` requests never touch NBU, so they always succeed regardless of NBU health.

---

## Seeding from DummyJSON

Import (or refresh) the catalog with:

```bash
php artisan products:seed
```

- Fetches every smartphone from
  `https://dummyjson.com/products/category/smartphones` (paginating defensively).
- **Idempotent:** each product is written with `updateOrCreate()` keyed on its
  DummyJSON `id` (stored locally as `external_id`, a unique column). Running the
  command repeatedly refreshes existing rows in place and never creates
  duplicates. The command prints how many rows were created vs. updated.

Seeding is deliberately an **Artisan command**, not an HTTP endpoint — importing
external data is an operational/setup task and should not be publicly triggerable.

Implemented in
[`app/Console/Commands/SeedProductsCommand.php`](app/Console/Commands/SeedProductsCommand.php)
and [`app/Services/DummyJsonClient.php`](app/Services/DummyJsonClient.php).

---

## Database schema

A single `products` table. Prices are stored in USD; JSON-shaped fields
(dimensions, tags, images, meta) use JSON columns.

| Column                   | Type                           | Notes                                                                      |
| ------------------------ | ------------------------------ | -------------------------------------------------------------------------- |
| `id`                     | `bigint` (PK, auto-increment)  | Local identifier used by the API.                                          |
| `external_id`            | `bigint`, nullable, **unique** | DummyJSON id. Enables idempotent seeding. `null` for API-created products. |
| `title`                  | `varchar`                      | Required.                                                                  |
| `description`            | `text`, nullable               |                                                                            |
| `category`               | `varchar`, nullable            |                                                                            |
| `price`                  | `decimal(12,2)`                | **Stored in USD.**                                                         |
| `discount_percentage`    | `decimal(5,2)`, nullable       |                                                                            |
| `rating`                 | `decimal(3,2)`, nullable       |                                                                            |
| `stock`                  | `integer`, nullable            |                                                                            |
| `brand`                  | `varchar`, nullable, indexed   | Drives the `?brand=` filter.                                               |
| `sku`                    | `varchar`, nullable            |                                                                            |
| `weight`                 | `decimal(8,2)`, nullable       |                                                                            |
| `dimensions`             | `json`, nullable               | `{ width, height, depth }`.                                                |
| `warranty_information`   | `varchar`, nullable            |                                                                            |
| `shipping_information`   | `varchar`, nullable            |                                                                            |
| `availability_status`    | `varchar`, nullable            |                                                                            |
| `return_policy`          | `text`, nullable               |                                                                            |
| `minimum_order_quantity` | `integer`, nullable            |                                                                            |
| `tags`                   | `json`, nullable               |                                                                            |
| `images`                 | `json`, nullable               |                                                                            |
| `thumbnail`              | `varchar`, nullable            |                                                                            |
| `meta`                   | `json`, nullable               | Barcode, QR code, etc.                                                     |
| `created_at`             | `timestamp`                    |                                                                            |
| `updated_at`             | `timestamp`                    |                                                                            |

**Relationships:** none. The catalog is a single entity. DummyJSON `reviews` are
intentionally out of scope — this service is a smartphone _catalog_, and modelling
reviews would add a table and endpoints the assignment does not ask for.

Defined in
[`database/migrations/*_create_products_table.php`](database/migrations).

---

## How the application is organised

The design keeps controllers thin and pushes real work into single-responsibility
classes, so each concern is easy to find, test, and reason about.

```
app/
├── Console/Commands/
│   └── SeedProductsCommand.php     # `products:seed` — orchestrates the import
├── Exceptions/
│   └── ExchangeRateUnavailableException.php  # → HTTP 503 (registered in bootstrap/app.php)
├── Http/
│   ├── Controllers/Api/
│   │   └── ProductController.php   # thin CRUD; delegates conversion & validation
│   ├── Requests/
│   │   ├── StoreProductRequest.php   # create validation
│   │   └── UpdateProductRequest.php  # PATCH validation (all rules `sometimes`)
│   └── Resources/
│       └── ProductResource.php     # response shape + price conversion
├── Models/
│   └── Product.php                 # fillable + casts
└── Services/
    ├── DummyJsonClient.php         # talks to DummyJSON
    ├── NbuExchangeRateService.php  # fetches + caches NBU rates
    └── CurrencyConverter.php       # USD → USD/UAH/EUR conversion logic
config/catalog.php                  # external API URLs & timeouts (env-overridable)
```

Key decisions:

- **Service classes** isolate the two integrations (DummyJSON, NBU) and the
  conversion maths from HTTP and CLI entry points, so both the controller and the
  seed command reuse them and tests can target them precisely.
- **Form Requests** hold validation. `UpdateProductRequest` derives its rules from
  `StoreProductRequest` and marks them all `sometimes`, giving true PATCH
  semantics (only supplied fields are validated/updated) with no rule duplication.
- **API Resource** owns the response shape and performs currency conversion, so
  every endpoint returns a product consistently.
- **`external_id`** is a unique column that both makes seeding idempotent and
  distinguishes DummyJSON-sourced products from API-created ones. It is never
  client-settable — controllers persist only `FormRequest::validated()` data.
- **Config over hard-coding:** external URLs/timeouts live in `config/catalog.php`
  and are environment-overridable, which also makes them trivial to point
  elsewhere in tests.

---

## Testing

The suite uses **Pest** feature tests that exercise the real HTTP stack, with all
outbound calls to DummyJSON and NBU faked via Laravel's HTTP client — so tests are
fast, deterministic, and never touch the network.

Tests run against an **in-memory SQLite** database (configured in `phpunit.xml`),
so **you do not need Docker or MySQL running to run the tests** — a single command
is fully self-contained:

```bash
php artisan test
# or
./vendor/bin/pest
```

Coverage includes:

- **CRUD:** listing, pagination (`page`/`limit`), `brand` filtering, show, create
  (incl. `422` validation and negative-price rejection), partial PATCH, delete,
  and `404`s.
- **Currency:** USD default, UAH direct conversion, EUR cross-rate, case-insensitive
  input, per-listing conversion, unsupported-currency `422`, NBU-down `503`, and
  stale-cache fallback.
- **Seeding:** import correctness (incl. JSON column mapping), idempotency across
  repeated runs, and in-place updates when upstream data changes.

---

## Code style

PHP is formatted with [Laravel Pint](https://laravel.com/docs/pint) (the
`laravel` preset, configured in `pint.json`). Markdown, YAML and JSON follow the
[Prettier](https://prettier.io) config in `.prettierrc`.

```bash
composer format   # auto-format all PHP (vendor/bin/pint)
composer lint     # check formatting without changing files (pint --test)
```

`composer lint` is CI-friendly — it exits non-zero if anything is unformatted.

---

## How AI was used

This project was built with AI assistance (Claude), used as a pair-programmer:
I made the product/architecture decisions and reviewed every change; AI wrote
code and docs to that spec. Step by step, in the order it happened:

1. **Chose the stack and planned the approach** _(my decision)._ Requirements
   were: a smartphone catalog seeded from DummyJSON, CRUD, and USD/UAH/EUR
   pricing via live NBU rates. I decided on Laravel 13 + Pest + MySQL (via
   Docker) for the app, with SQLite in-memory for tests so the suite needs no
   external services, and a thin-controller/service-class split so the two
   external integrations (DummyJSON, NBU) and the conversion maths stay
   independently testable. AI scaffolded the initial project (`composer.json`,
   `docker-compose.yml`, `.env.example`, `phpunit.xml`) to match.
   → [`Set up Laravel 13 project with Docker MySQL and Pest`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/56e1031)

2. **Implemented the plan** _(AI's implementation)._ Built out in
   layers, each as its own commit so the diffs stayed reviewable: the
   `products` migration/model/factory; the `DummyJsonClient`, cross-rate
   `CurrencyConverter`, and `NbuExchangeRateService` (with the
   cache → stale-fallback → 503 strategy); the CRUD controller, Form Requests,
   and `ProductResource`; then the idempotent `products:seed` command
   (`updateOrCreate()` keyed on `external_id`). I specified the behaviour I
   wanted at each step (e.g. "PATCH should only touch fields that are actually
   present," "NBU going down should degrade to a stale rate before failing");
   AI wrote the code and I read every file before moving on.
   → commits `4a99449`, `fe1bd5a`, `9ea10c0`, `922f7b5`

3. **Wrote the test suite** _(coverage requirements, AI's test code)._ I
   specified what needed covering — CRUD incl. validation/`404`s, currency
   conversion incl. the `503` and stale-cache paths, and seed idempotency — and
   asked for outbound HTTP (DummyJSON, NBU) to be faked so tests never hit the
   network. AI wrote the Pest feature tests to that brief.
   → [`Add Pest feature tests for CRUD, currency and seeding`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/ccbb84c)

4. **Added documentation** _(my idea, AI's draft)._ Asked for this README plus
   a frontend-facing API doc and an architecture walkthrough. AI drafted all
   three. On review I decided the separate `ARCHITECTURE.md`/`API_DOCS.md`
   files duplicated what's already covered by this README and by Scramble's
   live OpenAPI docs (`/docs/api`) — two sources of truth that would drift out
   of sync — so I had them removed rather than maintained.
   → [`Add project README`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/1b2888e),
   [`Add docs for frontend engineers and prettier`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/ef5f0cb),
   [`Remove local architecture files`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/2ed0ea3)

5. **Added CI and formatting** _(my idea, AI's implementation)._ Asked for a
   GitHub Actions pipeline covering Pint linting, `composer audit`, the Pest
   suite, and a migrations-against-real-MySQL smoke test — plus Pint/Prettier
   config so style is enforced automatically rather than by review comments.
   → [`Add CI`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/55550c7)

6. **Found a CI failure and fixed it** _(AI's fix)._ The first CI run
   failed on the `tests` job with `Test directory ".../tests/Unit" not
   found`. `phpunit.xml` declares a `Unit` test suite pointed at
   `tests/Unit`, but at that point every test written so far was a Feature
   test — the `Unit` folder had never held a file. Git doesn't track empty
   directories, so on a clean CI checkout the path didn't exist on disk at
   all, and `php artisan test` exited with code `2` before running anything.
   I read the Actions log, diagnosed it as a missing directory rather than a
   broken test, and asked for real unit coverage there; AI added an isolated
   `CurrencyConverterTest` (a stubbed rate service, no HTTP/DB at all) to
   `tests/Unit`, which fixed the run by giving the declared suite a
   directory to find.
   → [`Fix test ci`](https://github.com/MariiaMonakhova/smartphone-catalog-api/commit/5577ccb)

I'm happy to walk through any design decision — the currency cross-rate model,
the idempotent-seed strategy, the NBU fallback behaviour, or the
thin-controller/service split — in a follow-up review.
