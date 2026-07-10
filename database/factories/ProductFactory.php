<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => 'smartphones',
            'price' => fake()->randomFloat(2, 50, 2000),
            'discount_percentage' => fake()->randomFloat(2, 0, 25),
            'rating' => fake()->randomFloat(2, 1, 5),
            'stock' => fake()->numberBetween(0, 100),
            'brand' => fake()->randomElement(['Apple', 'Samsung', 'Xiaomi', 'OnePlus', 'Google']),
            'sku' => strtoupper(fake()->bothify('SMA-???-####')),
            'weight' => fake()->randomFloat(2, 1, 10),
            'dimensions' => [
                'width' => fake()->randomFloat(2, 5, 20),
                'height' => fake()->randomFloat(2, 5, 20),
                'depth' => fake()->randomFloat(2, 5, 20),
            ],
            'warranty_information' => fake()->randomElement(['1 year warranty', '2 year warranty', 'Lifetime warranty']),
            'shipping_information' => fake()->randomElement(['Ships in 1 week', 'Ships overnight', 'Ships in 1 month']),
            'availability_status' => fake()->randomElement(['In Stock', 'Low Stock']),
            'return_policy' => fake()->randomElement(['30 days return policy', '60 days return policy', 'No return policy']),
            'minimum_order_quantity' => fake()->numberBetween(1, 5),
            'tags' => ['smartphones', fake()->word()],
            'images' => [fake()->imageUrl()],
            'thumbnail' => fake()->imageUrl(),
            'meta' => ['barcode' => fake()->ean13(), 'qrCode' => fake()->url()],
        ];
    }
}
