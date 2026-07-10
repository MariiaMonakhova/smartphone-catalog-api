# API Documentation (for frontend developers)

The API ships with **interactive, always-up-to-date OpenAPI docs** generated
directly from the code, so what you see is exactly what the server does.

## Opening the docs

1. Start the backend (see the main [README](../README.md) — `docker compose up -d`,
   `php artisan migrate`, `php artisan products:seed`, `php artisan serve`).
2. Open **<http://127.0.0.1:8000/docs/api>** in your browser.

You'll get a full reference of every endpoint with parameters, request/response
schemas, status codes, and example payloads.

> The docs page is available in local/dev by default. In `production` it is
> locked down (see `RestrictedDocsAccess` in `config/scramble.php`), so it won't
> leak publicly.

## Trying requests live ("Try It")

Each endpoint has a **Try It** panel: fill in parameters/body, hit **Send**, and
the request goes to your running local server. Use it to:

- List products and experiment with `?brand=`, `?limit=`, `?page=`.
- See real currency conversion by setting `?currency=EUR` or `?currency=UAH`.
- Create / update / delete products without writing any client code first.

## The raw spec (for code generation & tooling)

The machine-readable OpenAPI 3 document is served at:

**<http://127.0.0.1:8000/docs/api.json>**

You can point tooling straight at it, for example:

```bash
# Generate a typed TypeScript client
npx openapi-typescript http://127.0.0.1:8000/docs/api.json -o src/api/schema.ts

# Import into Postman / Insomnia / Bruno: "Import > OpenAPI" and paste the URL
```

Or export it to a file from the CLI (no server needed):

```bash
php artisan scramble:export        # writes api.json to the project root
```

## What frontend devs most need to know

- **All prices are stored in USD.** Add `?currency=USD|EUR|UAH` to `GET /products`
  and `GET /products/{id}` to get a converted `price`. Every product also returns
  `price_usd` (the untouched USD value) and `currency` (what `price` is in).
- **Partial updates:** `PATCH /products/{id}` changes only the fields you send.
- **Status codes you'll handle:** `200`/`201`/`204` success · `404` unknown id ·
  `422` validation errors (body includes an `errors` map) · `503` when a currency
  conversion is requested but exchange rates are temporarily unavailable (USD
  requests never fail this way — retry shortly).

## Example response

`GET /api/products/1?currency=EUR`

```json
{
  "data": {
    "id": 1,
    "title": "iPhone 5s",
    "brand": "Apple",
    "currency": "EUR",
    "price": 174.96,
    "price_usd": 199.99,
    "stock": 25,
    "dimensions": { "width": 5.29, "height": 18.38, "depth": 17.72 }
  }
}
```
