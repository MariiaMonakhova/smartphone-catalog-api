<?php

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

it('defaults to USD when no currency is given', function () {
    $product = Product::factory()->create(['price' => 100]);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.price', fn ($v) => (float) $v === 100.0)
        ->assertJsonPath('data.price_usd', fn ($v) => (float) $v === 100.0);
});

it('converts a price to UAH using the direct NBU rate', function () {
    fakeNbuRates();
    $product = Product::factory()->create(['price' => 100]);

    $expected = round(100 * NBU_USD_RATE, 2);

    $this->getJson("/api/products/{$product->id}?currency=UAH")
        ->assertOk()
        ->assertJsonPath('data.currency', 'UAH')
        ->assertJsonPath('data.price', fn ($v) => (float) $v === $expected)
        ->assertJsonPath('data.price_usd', fn ($v) => (float) $v === 100.0);
});

it('converts a price to EUR using the UAH cross-rate', function () {
    fakeNbuRates();
    $product = Product::factory()->create(['price' => 100]);

    $expected = round(100 * NBU_USD_RATE / NBU_EUR_RATE, 2);

    $this->getJson("/api/products/{$product->id}?currency=EUR")
        ->assertOk()
        ->assertJsonPath('data.currency', 'EUR')
        ->assertJsonPath('data.price', fn ($v) => (float) $v === $expected);
});

it('accepts a lowercase currency code', function () {
    fakeNbuRates();
    $product = Product::factory()->create(['price' => 100]);

    $this->getJson("/api/products/{$product->id}?currency=uah")
        ->assertOk()
        ->assertJsonPath('data.currency', 'UAH');
});

it('applies conversion across the index listing', function () {
    fakeNbuRates();
    Product::factory()->create(['price' => 200]);

    $expected = round(200 * NBU_USD_RATE, 2);

    $this->getJson('/api/products?currency=UAH')
        ->assertOk()
        ->assertJsonPath('data.0.currency', 'UAH')
        ->assertJsonPath('data.0.price', fn ($v) => (float) $v === $expected);
});

it('rejects an unsupported currency', function () {
    $product = Product::factory()->create();

    $this->getJson("/api/products/{$product->id}?currency=GBP")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['currency']);
});

it('returns 503 when NBU is unavailable and nothing is cached', function () {
    fakeNbuDown();
    $product = Product::factory()->create(['price' => 100]);

    $this->getJson("/api/products/{$product->id}?currency=UAH")
        ->assertStatus(503);
});

it('serves the stale cached rate when NBU is down', function () {
    // Seed a "last known good" rate, leave the daily cache cold, then break NBU.
    Cache::forever('nbu:rate:USD:last_good', 40.0);
    fakeNbuDown();

    $product = Product::factory()->create(['price' => 100]);

    $this->getJson("/api/products/{$product->id}?currency=UAH")
        ->assertOk()
        ->assertJsonPath('data.price', fn ($v) => (float) $v === 4000.0);
});

it('does not call NBU for USD requests', function () {
    fakeNbuDown(); // would 503 if it were called
    $product = Product::factory()->create(['price' => 100]);

    $this->getJson("/api/products/{$product->id}?currency=USD")
        ->assertOk()
        ->assertJsonPath('data.price', fn ($v) => (float) $v === 100.0);
});
