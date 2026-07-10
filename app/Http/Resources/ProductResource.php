<?php

namespace App\Http\Resources;

use App\Services\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Prices are stored in USD. If the request carries a ?currency= value we
     * expose the converted `price` alongside the untouched `price_usd`, plus
     * the `currency` the `price` is expressed in — so a consumer always knows
     * exactly what they are looking at.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = strtoupper($request->query('currency', 'USD'));
        $priceUsd = (float) $this->price;

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,

            'currency' => $currency,
            'price' => app(CurrencyConverter::class)->convert($priceUsd, $currency),
            'price_usd' => round($priceUsd, 2),

            'discount_percentage' => $this->discount_percentage !== null ? (float) $this->discount_percentage : null,
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'stock' => $this->stock,
            'brand' => $this->brand,
            'sku' => $this->sku,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'dimensions' => $this->dimensions,
            'warranty_information' => $this->warranty_information,
            'shipping_information' => $this->shipping_information,
            'availability_status' => $this->availability_status,
            'return_policy' => $this->return_policy,
            'minimum_order_quantity' => $this->minimum_order_quantity,
            'tags' => $this->tags,
            'images' => $this->images,
            'thumbnail' => $this->thumbnail,
            'meta' => $this->meta,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
