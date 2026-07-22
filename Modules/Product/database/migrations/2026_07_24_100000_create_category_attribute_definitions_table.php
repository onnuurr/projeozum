<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 3 (Attribute Engine): kategoriye göre değişen ürün özelliği tanımları
 * (yaka/kol/kumaş/desen/fit vb.). Kategoriye 1:1 bağlı, kalıtım yok (bkz. plan
 * Kapsam kararı 1) — `category_marketplace_mappings`'in birebir eşi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            $table->enum('type', ['string', 'enum', 'number', 'boolean'])->default('string');
            $table->jsonb('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_definitions');
    }
};
