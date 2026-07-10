<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin client for the DummyJSON public API used to seed the local catalog.
 */
class DummyJsonClient
{
    /**
     * Fetch every smartphone from DummyJSON.
     *
     * DummyJSON returns paginated results ({ products, total, skip, limit }).
     * We page through explicitly so the command works even if the category
     * ever grows beyond a single response.
     *
     * @return array<int, array<string, mixed>> Raw product arrays.
     */
    public function fetchSmartphones(): array
    {
        $url = (string) config('catalog.dummyjson_url');
        $timeout = (int) config('catalog.dummyjson_timeout', 15);

        $limit = 100;
        $skip = 0;
        $all = [];

        do {
            $response = Http::timeout($timeout)
                ->retry(2, 200)
                ->get($url, ['limit' => $limit, 'skip' => $skip])
                ->throw();

            $data = $response->json();
            $products = $data['products'] ?? [];
            $all = array_merge($all, $products);

            $total = (int) ($data['total'] ?? count($all));
            $skip += $limit;
        } while (count($products) > 0 && count($all) < $total);

        return $all;
    }
}
