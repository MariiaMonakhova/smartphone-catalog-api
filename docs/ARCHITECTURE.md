# Architecture & Request Lifecycle

This document explains **how the pieces fit together** — where a request starts,
what each file does as the request flows through, where it ends, and *why* the
code is split up the way it is. If `README.md` is "what it does and how to run
it", this is "how it works inside".

---

## 1. The mental model

The app is a thin HTTP/CLI shell around three plain PHP service classes. Nothing
clever happens in controllers or commands — they **receive input, delegate, and
format output**. All real logic (talking to external APIs, converting money)
lives in `app/Services`, so it can be reused and tested in isolation.

```
                        ┌──────────────────────────────────────────┐
   HTTP request ───►    │  Route → Controller → (Request validation) │
                        │            │                                │
                        │            ├── Model (Eloquent)  ──► MySQL   │
                        │            │                                │
                        │            └── Resource ──► CurrencyConverter │
                        │                                  │           │
                        │                                  └── NbuExchangeRateService
                        │                                        │ (cache, then HTTP)
   JSON response ◄───   │                                        └──► bank.gov.ua
                        └──────────────────────────────────────────┘

   CLI (`products:seed`) ──► SeedProductsCommand ──► DummyJsonClient ──► dummyjson.com
                                     └── Model (updateOrCreate) ──► MySQL
```

Two independent entry points, one shared domain (the `Product` model + services).

---

## 2. The files, by layer

| Layer | File | Responsibility |
|-------|------|----------------|
| **Entry (HTTP)** | `bootstrap/app.php` | Boots the framework, wires `routes/api.php`, registers the global exception → JSON rules (incl. the 503 handler). |
| | `routes/api.php` | Maps `/api/products` URLs to `ProductController` via `apiResource`. |
| **Controller** | `app/Http/Controllers/Api/ProductController.php` | One thin method per endpoint. Validates query params, delegates, returns Resources. |
| **Validation** | `app/Http/Requests/StoreProductRequest.php` | Rules for creating a product. |
| | `app/Http/Requests/UpdateProductRequest.php` | Same rules as Store, made optional (`sometimes`) for PATCH. |
| **Serialization** | `app/Http/Resources/ProductResource.php` | Shapes the JSON for one product **and** converts its price to the requested currency. |
| **Domain model** | `app/Models/Product.php` | Eloquent model: `$fillable` (mass-assignment allow-list) + `$casts` (JSON/decimal columns). |
| **Services** | `app/Services/DummyJsonClient.php` | Fetches smartphones from DummyJSON (used only by the seed command). |
| | `app/Services/NbuExchangeRateService.php` | Fetches + **caches** NBU rates; handles NBU being down. |
| | `app/Services/CurrencyConverter.php` | Pure conversion maths: USD → USD/UAH/EUR. |
| **Errors** | `app/Exceptions/ExchangeRateUnavailableException.php` | Thrown when no rate is available → becomes HTTP 503. |
| **CLI entry** | `app/Console/Commands/SeedProductsCommand.php` | `php artisan products:seed`; orchestrates the import. |
| **Config** | `config/catalog.php` | External API URLs + timeouts (env-overridable). |
| **Schema** | `database/migrations/*_create_products_table.php` | The `products` table definition. |

The key idea: **dependencies point downward** (controller → resource → services →
model), and the services never know about HTTP. That's what makes them reusable
by both the API and the CLI command, and easy to unit/feature test.

---

## 3. Request lifecycle: the richest path

### `GET /api/products/1?currency=EUR`

Follow the numbers; each step names the file doing the work.

```
1. HTTP request hits public/index.php
2. bootstrap/app.php boots Laravel, loads routes/api.php
3. routes/api.php: apiResource matched → ProductController@show
4. Route-model binding: Laravel takes "1", runs Product::findOrFail(1)
      ├─ found  → injects the Product instance as $product
      └─ missing→ throws ModelNotFoundException → 404 JSON (never enters our method)
5. ProductController@show runs:
      a. normalizeCurrency(): "eur"/"EUR" → "EUR" (case-insensitive)
      b. $request->validate(['currency' => in:USD,EUR,UAH])
             └─ invalid → ValidationException → 422 JSON (auto)
      c. returns new ProductResource($product)
6. ProductResource@toArray runs while Laravel serializes the response:
      a. reads currency from the request ("EUR")
      b. priceUsd = (float) $this->price          // the stored USD value
      c. app(CurrencyConverter::class)->convert(priceUsd, "EUR")
7. CurrencyConverter@convert:
      EUR → priceUsd * rate("USD") / rate("EUR")   // cross-rate through UAH
      each rate(...) call ↓
8. NbuExchangeRateService@rate("USD"):
      a. Cache::get("nbu:rate:USD")  → hit?  return it (no network)
      b. miss → fetchRate() → HTTP GET bank.gov.ua ...valcode=USD
             ├─ success → cache until end of day + store "…:last_good" forever
             └─ failure → use "…:last_good" if present, else throw
                          ExchangeRateUnavailableException
9. Response assembled: { "data": { ...product..., "currency":"EUR",
                                   "price": <converted>, "price_usd": <original> } }
10. bootstrap/app.php's shouldRenderJsonWhen ensures any thrown exception on
    api/* is JSON. The 503 handler turns ExchangeRateUnavailableException into
    { "message": ... } with status 503.
```

**Where it starts:** `public/index.php` → `bootstrap/app.php`.
**Where it ends:** the `ProductResource` array becomes the JSON body; Laravel
sends it. Any exception short-circuits to the exception handler in
`bootstrap/app.php`.

Notice the controller method is ~5 lines: it does **not** know how conversion or
caching works. It just asks for a `ProductResource`; the resource asks the
`CurrencyConverter`; the converter asks the `NbuExchangeRateService`. Each layer
only knows about the one below it.

---

## 4. The other HTTP paths (shorter)

### `GET /api/products?brand=Apple&limit=5&currency=UAH`
`ProductController@index` validates the query params, builds an Eloquent query
(`when(brand)` adds a `where`, `paginate($limit)` handles paging), and returns
`ProductResource::collection(...)`. Each item in the collection runs the same
`toArray` → `CurrencyConverter` path as above. Pagination metadata (`meta`,
`links`) is added by Laravel's paginator automatically.

### `POST /api/products`
The method signature type-hints `StoreProductRequest`. **Validation runs before
the method body** — Laravel resolves the FormRequest, validates, and only calls
`store()` if it passes (otherwise 422). Inside, `Product::create($request->
validated())` persists only validated fields (so a client can't set
`external_id` or any unknown column — mass-assignment safety). Returns `201`.

### `PATCH /api/products/1`
Uses `UpdateProductRequest`, whose rules are the Store rules with `required`
removed and `sometimes` prepended. Result: only fields **present** in the body
are validated and updated; everything else is untouched. That's real PATCH
semantics with zero rule duplication. Returns the updated product.

### `DELETE /api/products/1`
`destroy()` deletes and returns `204 No Content`. A missing id 404s via
route-model binding before the method runs.

---

## 5. The CLI path: `php artisan products:seed`

```
1. Artisan resolves SeedProductsCommand (signature: products:seed)
2. handle(DummyJsonClient $client)   // dependency injected by the container
3. $client->fetchSmartphones():
      loops GET dummyjson.com/products/category/smartphones?limit=..&skip=..
      until every product is fetched → returns raw arrays
4. For each raw product:
      Product::updateOrCreate(['external_id' => $raw['id']], map($raw))
         ├─ external_id already exists → UPDATE that row
         └─ new                        → INSERT
      map() renames DummyJSON's camelCase keys → our snake_case columns
5. Prints "Created X, updated Y"
```

**Why `updateOrCreate` on `external_id`:** it makes the command **idempotent**.
`external_id` is a unique column holding DummyJSON's own id, so re-running the
command refreshes existing rows in place and never creates duplicates — safe to
run on every deploy. Seeding is a command, not an HTTP endpoint, because
importing third-party data is an operational task and shouldn't be publicly
triggerable.

The command reuses `DummyJsonClient` — the same service the app could use
elsewhere — instead of embedding HTTP logic in the command.

---

## 6. Cross-cutting concerns

### Error handling — one place, `bootstrap/app.php`
- `shouldRenderJsonWhen(api/*)` → every error on an API route comes back as JSON,
  not an HTML error page.
- The `render(ExchangeRateUnavailableException ...)` closure maps that specific
  exception to **503**.
- `404` (missing product) and `422` (validation) are produced automatically by
  Laravel from `ModelNotFoundException` / `ValidationException` — we don't write
  any code for them.

### Caching — inside `NbuExchangeRateService`
- `nbu:rate:{CC}` cached **until end of day** → NBU is hit at most once per
  currency per day.
- `nbu:rate:{CC}:last_good` cached **forever** → a stale fallback used only when
  the daily cache is cold *and* NBU is unreachable.
- Order of preference: fresh cache → stale fallback → 503.
- USD needs no rate, so USD requests never touch NBU or the cache.

### Configuration — `config/catalog.php`
External URLs and timeouts are config values (env-overridable), not hard-coded
strings. This keeps integration details in one place and lets tests point the
services elsewhere if needed.

### Why store USD and convert on read (not store per-currency)
Rates change daily; prices don't. Storing one canonical USD value and converting
at read time means there's a single source of truth and no stale converted
prices to keep in sync. The trade-off (a possible NBU call per read) is absorbed
by the daily cache.

---

## 7. Interactive API docs (Scramble / OpenAPI)

`GET /docs/api` serves an interactive OpenAPI UI (with a **Try It** panel), and
`GET /docs/api.json` serves the raw OpenAPI 3 spec. These are generated by
[Scramble](https://scramble.dedoc.co) directly from the code you see above — it
reads the controller type-hints, the FormRequest rules, the `Rule::in` on
`currency`, and `ProductResource::toArray` to infer paths, parameters, request
bodies, response shapes, and status codes. There are no separate annotation
files to keep in sync; the docs are a projection of the real code.

See [`API_DOCS.md`](API_DOCS.md) for how to open and use it.

---

## 8. One-screen summary

- **HTTP entry:** `public/index.php` → `bootstrap/app.php` → `routes/api.php` → `ProductController`.
- **Validation:** FormRequests (`Store`/`Update`) for bodies; inline `validate()` for query params.
- **Output:** `ProductResource` — also the single place price conversion happens.
- **Money logic:** `CurrencyConverter` (maths) → `NbuExchangeRateService` (rates + cache + fallback).
- **Import:** `products:seed` → `DummyJsonClient` → `Product::updateOrCreate`.
- **Errors:** centralised in `bootstrap/app.php` (503 explicit; 404/422 automatic).
- **Persistence:** one `Product` Eloquent model over one `products` table; prices stored in USD.
```
