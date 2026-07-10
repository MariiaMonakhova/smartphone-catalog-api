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
     * GET /api/products
     *
     * Supports ?page=, ?limit=, ?brand= and ?currency=.
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
     * POST /api/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * GET /api/products/{id}
     *
     * Supports ?currency=.
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
     * PATCH /api/products/{id}
     *
     * Partial update — only the fields present in the body are changed.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * DELETE /api/products/{id}
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
