<?php

use App\Models\Product;

it('lists products with default pagination', function () {
    Product::factory()->count(25)->create();

    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.per_page', 20);
});

it('respects the limit query parameter', function () {
    Product::factory()->count(10)->create();

    $this->getJson('/api/products?limit=5')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.per_page', 5);
});

it('paginates with the page query parameter', function () {
    Product::factory()->count(10)->create();

    $this->getJson('/api/products?limit=4&page=3')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 3);
});

it('filters products by brand', function () {
    Product::factory()->count(3)->create(['brand' => 'Apple']);
    Product::factory()->count(2)->create(['brand' => 'Samsung']);

    $this->getJson('/api/products?brand=Apple')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.brand', 'Apple');
});

it('shows a single product', function () {
    $product = Product::factory()->create(['title' => 'Galaxy S99']);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.title', 'Galaxy S99');
});

it('returns 404 for a missing product', function () {
    $this->getJson('/api/products/999999')
        ->assertNotFound();
});

it('creates a product', function () {
    $payload = [
        'title' => 'Pixel 42',
        'price' => 799.99,
        'brand' => 'Google',
        'stock' => 12,
    ];

    $this->postJson('/api/products', $payload)
        ->assertCreated()
        ->assertJsonPath('data.title', 'Pixel 42')
        ->assertJsonPath('data.price_usd', 799.99)
        ->assertJsonPath('data.brand', 'Google');

    $this->assertDatabaseHas('products', [
        'title' => 'Pixel 42',
        'brand' => 'Google',
    ]);
});

it('rejects a product without required fields', function () {
    $this->postJson('/api/products', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'price']);
});

it('rejects a negative price', function () {
    $this->postJson('/api/products', ['title' => 'X', 'price' => -5])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

it('ignores a client-supplied external_id on create', function () {
    $this->postJson('/api/products', [
        'title' => 'No spoofing',
        'price' => 100,
        'external_id' => 777,
    ])->assertCreated();

    $this->assertDatabaseMissing('products', ['external_id' => 777]);
});

it('partially updates only the fields provided', function () {
    $product = Product::factory()->create([
        'title' => 'Original',
        'price' => 500,
        'brand' => 'Apple',
    ]);

    $this->patchJson("/api/products/{$product->id}", ['price' => 650])
        ->assertOk()
        ->assertJsonPath('data.price_usd', fn ($v) => (float) $v === 650.0)
        ->assertJsonPath('data.title', 'Original')   // unchanged
        ->assertJsonPath('data.brand', 'Apple');     // unchanged

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'title' => 'Original',
        'price' => 650,
    ]);
});

it('deletes a product', function () {
    $product = Product::factory()->create();

    $this->deleteJson("/api/products/{$product->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);

    $this->getJson("/api/products/{$product->id}")->assertNotFound();
});
