<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('size', 16)->nullable();
            $table->string('color_name', 32)->nullable();
            $table->string('color_hex', 9)->nullable();
            $table->string('sku', 64)->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('old_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Aynı (renk, beden) kombinasyonu bir ürün için tek kayıt
            $table->unique(['product_id', 'size', 'color_name'], 'pv_combo_unique');
            $table->index(['product_id', 'stock']);
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
