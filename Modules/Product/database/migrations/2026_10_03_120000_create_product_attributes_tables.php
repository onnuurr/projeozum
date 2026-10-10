<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global ürün özellikleri (Ürün Ayarları → Özellikler, product-attribute.* yetkileri). Bagisto'daki
 * aynı kodlu attribute'un ana kaynağıdır (PushProductAttributeToBagisto); seçenekler Bagisto
 * attribute seçeneklerine ad ile eşlenir (PushAttributeOptionToBagisto).
 *
 * Kategoriler hangi özelliklerin ürün formunda görüneceğini/zorunlu olduğunu seçer
 * (category_product_attribute, alt kategoriler üst kategorinin seçimini miras alır).
 * Ürün değerleri products.attributes jsonb'de koda göre tutulur: seçimde seçenek id'si,
 * çoklu seçimde id dizisi — seçenek yeniden adlandırılınca ürün değerleri bozulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('type', 16);
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('product_attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->string('name', 191);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_attribute_id', 'name']);
        });

        Schema::create('category_product_attribute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->foreignId('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->unique(['category_id', 'product_attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product_attribute');
        Schema::dropIfExists('product_attribute_options');
        Schema::dropIfExists('product_attributes');
    }
};
