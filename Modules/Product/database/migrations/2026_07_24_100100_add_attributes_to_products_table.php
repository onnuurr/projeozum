<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 3 (Attribute Engine): kategoriye göre değişen, serbest biçimli ürün
 * özellikleri (`category_attribute_definitions` ile doğrulanır). GIN index
 * bilerek eklenmedi — bugün hiçbir sorgu bu kolona göre filtrelemiyor
 * (ProductController::index tamamen client-side filtreli), gerçek bir
 * filtre ihtiyacı doğduğunda ayrı bir migration'la eklenecek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('attributes')->nullable()->after('care_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });
    }
};
