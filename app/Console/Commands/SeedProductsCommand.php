<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\DummyJsonClient;
use Illuminate\Console\Command;
use Throwable;

class SeedProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all smartphones from the DummyJSON API into the local catalog (idempotent).';

    /**
     * Execute the console command.
     */
    public function handle(DummyJsonClient $client): int
    {
        $this->info('Fetching smartphones from DummyJSON...');

        try {
            $products = $client->fetchSmartphones();
        } catch (Throwable $e) {
            $this->error('Failed to fetch products from DummyJSON: '.$e->getMessage());

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        foreach ($products as $raw) {
            // updateOrCreate keyed on external_id makes re-runs idempotent:
            // existing rows are refreshed in place, never duplicated.
            $product = Product::updateOrCreate(
                ['external_id' => $raw['id']],
                $this->map($raw),
            );

            $product->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Done. Created {$created}, updated {$updated} (total ".count($products).').');

        return self::SUCCESS;
    }

    /**
     * Map a raw DummyJSON product onto local column names.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function map(array $raw): array
    {
        return [
            'title' => $raw['title'] ?? null,
            'description' => $raw['description'] ?? null,
            'category' => $raw['category'] ?? null,
            'price' => $raw['price'] ?? 0,
            'discount_percentage' => $raw['discountPercentage'] ?? null,
            'rating' => $raw['rating'] ?? null,
            'stock' => $raw['stock'] ?? null,
            'brand' => $raw['brand'] ?? null,
            'sku' => $raw['sku'] ?? null,
            'weight' => $raw['weight'] ?? null,
            'dimensions' => $raw['dimensions'] ?? null,
            'warranty_information' => $raw['warrantyInformation'] ?? null,
            'shipping_information' => $raw['shippingInformation'] ?? null,
            'availability_status' => $raw['availabilityStatus'] ?? null,
            'return_policy' => $raw['returnPolicy'] ?? null,
            'minimum_order_quantity' => $raw['minimumOrderQuantity'] ?? null,
            'tags' => $raw['tags'] ?? null,
            'images' => $raw['images'] ?? null,
            'thumbnail' => $raw['thumbnail'] ?? null,
            'meta' => $raw['meta'] ?? null,
        ];
    }
}
