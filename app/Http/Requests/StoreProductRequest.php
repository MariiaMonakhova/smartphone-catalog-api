<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for creating a product.
     *
     * `price` is required and expressed in USD. `external_id` is deliberately
     * not accepted — it only ever comes from the DummyJSON seed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.width' => ['nullable', 'numeric'],
            'dimensions.height' => ['nullable', 'numeric'],
            'dimensions.depth' => ['nullable', 'numeric'],
            'warranty_information' => ['nullable', 'string', 'max:255'],
            'shipping_information' => ['nullable', 'string', 'max:255'],
            'availability_status' => ['nullable', 'string', 'max:255'],
            'return_policy' => ['nullable', 'string'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'thumbnail' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
