<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /**
     * Attributes that are mass assignable.
     *
     * `external_id` is fillable so the seed command can updateOrCreate() on it,
     * but the public API never sets it: the store/update controllers only pass
     * FormRequest::validated() data, which cannot contain unvalidated fields.
     *
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'title',
        'description',
        'category',
        'price',
        'discount_percentage',
        'rating',
        'stock',
        'brand',
        'sku',
        'weight',
        'dimensions',
        'warranty_information',
        'shipping_information',
        'availability_status',
        'return_policy',
        'minimum_order_quantity',
        'tags',
        'images',
        'thumbnail',
        'meta',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'rating' => 'decimal:2',
            'weight' => 'decimal:2',
            'stock' => 'integer',
            'minimum_order_quantity' => 'integer',
            'dimensions' => 'array',
            'tags' => 'array',
            'images' => 'array',
            'meta' => 'array',
        ];
    }
}
