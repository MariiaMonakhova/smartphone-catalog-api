<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CurrencyConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * List products
     *
     * Returns a paginated list of products. Filter by `brand` and page with
     * `page`/`limit`. Pass `currency` (USD/EUR/UAH) to convert each product's
     * `price`; the original USD value is always returned as `price_usd`.
     *
     * Responds `503` if a non-USD currency is requested but the exchange rate is
     * unavailable.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->normalizeCurrency($request);

        $validated = $request->validate([
            'currency' => ['sometimes', Rule::in(CurrencyConverter::SUPPORTED)],
            'brand' => ['sometimes', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);

        $products = Product::query()
            ->when(
                $validated['brand'] ?? null,
                fn ($query, string $brand) => $query->where('brand', $brand)
            )
            ->orderBy('id')
            ->paginate($limit)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Create a product
     *
     * Creates a product from a JSON body. `price` is interpreted as USD.
     * Returns the created product with `201 Created`.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a product
     *
     * Returns a single product by its local database id. Pass `currency`
     * (USD/EUR/UAH) to convert `price`; `price_usd` always holds the stored USD
     * value. Responds `404` if no product has that id, and `503` if a non-USD
     * currency is requested but the exchange rate is unavailable.
     */
    public function show(Request $request, Product $product): ProductResource
    {
        $this->normalizeCurrency($request);

        $request->validate([
            'currency' => ['sometimes', Rule::in(CurrencyConverter::SUPPORTED)],
        ]);

        return new ProductResource($product);
    }

    /**
     * Update a product
     *
     * Partial update — only the fields present in the body are changed; omitted
     * fields keep their current values. `price` is interpreted as USD.
     * Responds `404` if no product has that id.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * Delete a product
     *
     * Permanently deletes a product. Returns `204 No Content` on success, or
     * `404` if no product has that id.
     */
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    /**
     * Accept ?currency= case-insensitively (e.g. "eur" -> "EUR") so validation
     * and downstream conversion see a canonical value.
     */
    private function normalizeCurrency(Request $request): void
    {
        if ($request->filled('currency')) {
            $request->merge([
                'currency' => strtoupper((string) $request->query('currency')),
            ]);
        }
    }
}
