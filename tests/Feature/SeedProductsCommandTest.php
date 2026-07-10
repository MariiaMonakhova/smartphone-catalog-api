<?php

use App\Models\Product;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

/**
 * Fake the DummyJSON smartphones endpoint with the given product rows.
 *
 * @param  array<int, array<string, mixed>>  $products
 */
function fakeDummyJson(array $products): void
{
    Http::fake(function (HttpRequest $request) use ($products) {
        if (! str_contains($request->url(), 'dummyjson.com')) {
            return null;
        }

        return Http::response([
            'products' => $products,
            'total' => count($products),
            'skip' => 0,
            'limit' => 100,
        ]);
    });
}

/**
 * @return array<int, array<string, mixed>>
 */
function sampleDummyProducts(): array
{
    return [
        [
            'id' => 1,
            'title' => 'iPhone 5s',
            'description' => 'A classic.',
            'category' => 'smartphones',
            'price' => 199.99,
            'discountPercentage' => 12.91,
            'rating' => 2.83,
            'stock' => 25,
            'brand' => 'Apple',
            'sku' => 'SMA-APP-IPH-1',
            'weight' => 2,
            'dimensions' => ['width' => 5.29, 'height' => 18.38, 'depth' => 17.72],
            'warrantyInformation' => 'Lifetime warranty',
            'shippingInformation' => 'Ships in 1 month',
            'availabilityStatus' => 'In Stock',
            'returnPolicy' => '60 days return policy',
            'minimumOrderQuantity' => 3,
            'tags' => ['smartphones', 'apple'],
            'images' => ['https://example.test/a.png'],
            'thumbnail' => 'https://example.test/thumb.png',
            'meta' => ['barcode' => '123', 'qrCode' => 'https://example.test/qr.png'],
        ],
        [
            'id' => 2,
            'title' => 'Galaxy S9',
            'category' => 'smartphones',
            'price' => 499.50,
            'brand' => 'Samsung',
        ],
    ];
}

it('imports smartphones from DummyJSON', function () {
    fakeDummyJson(sampleDummyProducts());

    $this->artisan('products:seed')->assertSuccessful();

    expect(Product::count())->toBe(2);

    $this->assertDatabaseHas('products', [
        'external_id' => 1,
        'title' => 'iPhone 5s',
        'brand' => 'Apple',
        'price' => 199.99,
        'discount_percentage' => 12.91,
    ]);

    // JSON columns are mapped and cast correctly.
    $iphone = Product::where('external_id', 1)->first();
    expect($iphone->dimensions)->toBe(['width' => 5.29, 'height' => 18.38, 'depth' => 17.72])
        ->and($iphone->tags)->toBe(['smartphones', 'apple']);
});

it('is idempotent across repeated runs', function () {
    fakeDummyJson(sampleDummyProducts());

    $this->artisan('products:seed')->assertSuccessful();
    $this->artisan('products:seed')->assertSuccessful();

    expect(Product::count())->toBe(2); // no duplicates
});

it('updates existing products instead of duplicating them', function () {
    // A single fake reading a by-reference payload, so the second seed sees
    // the changed upstream price. (Http::fake() *merges* stubs rather than
    // replacing them, so re-faking mid-test would let the stale stub win.)
    $products = sampleDummyProducts();

    Http::fake(function (HttpRequest $request) use (&$products) {
        if (! str_contains($request->url(), 'dummyjson.com')) {
            return null;
        }

        return Http::response([
            'products' => $products,
            'total' => count($products),
            'skip' => 0,
            'limit' => 100,
        ]);
    });

    $this->artisan('products:seed')->assertSuccessful();

    // Upstream price changes; re-seed.
    $products[0]['price'] = 149.00;
    $this->artisan('products:seed')->assertSuccessful();

    expect(Product::count())->toBe(2);
    $this->assertDatabaseHas('products', [
        'external_id' => 1,
        'price' => 149.00,
    ]);
});
