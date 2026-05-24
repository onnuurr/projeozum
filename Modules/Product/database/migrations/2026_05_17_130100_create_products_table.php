<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->string('name');
            $table->string('sku', 64)->unique();
            $table->enum('gender', ['Erkek', 'Kadın', 'Unisex'])->default('Unisex');

            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('old_price', 12, 2)->nullable();

            $table->unsignedInteger('stock')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);

            $table->boolean('is_new')->default(false);
            $table->boolean('free_shipping')->default(false);

            $table->timestamps();

            $table->index(['category_id', 'brand_id']);
            $table->index('gender');
            $table->index('price');
            $table->index('is_new');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
