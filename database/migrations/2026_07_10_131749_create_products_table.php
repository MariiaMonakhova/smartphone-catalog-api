<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // DummyJSON identifier. Nullable because products created through the
            // API have no upstream origin. Unique so the seed command can safely
            // updateOrCreate() on it and remain idempotent.
            $table->unsignedBigInteger('external_id')->nullable()->unique();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();

            // Prices are always stored in USD (the currency DummyJSON returns).
            // Conversion to UAH/EUR happens at read time via the NBU rate service.
            $table->decimal('price', 12, 2);

            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('stock')->nullable();

            $table->string('brand')->nullable()->index();
            $table->string('sku')->nullable();
            $table->decimal('weight', 8, 2)->nullable();

            $table->json('dimensions')->nullable();
            $table->string('warranty_information')->nullable();
            $table->string('shipping_information')->nullable();
            $table->string('availability_status')->nullable();
            $table->text('return_policy')->nullable();
            $table->integer('minimum_order_quantity')->nullable();

            $table->json('tags')->nullable();
            $table->json('images')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
