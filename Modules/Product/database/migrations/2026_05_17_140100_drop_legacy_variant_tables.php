<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('product_colors');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'stock')) {
                $table->dropColumn('stock');
            }
        });
    }

    /**
     * Geri alma: tabloları boş şemayla yeniden oluştur, products.stock kolonunu geri ekle.
     * Veri kurtarılmaz — variants tablosundan manuel olarak çevrilmesi gerekir.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->after('old_price');
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['product_id', 'size']);
            $table->index('size');
        });

        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 32);
            $table->string('hex', 9);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['product_id', 'name']);
        });
    }
};
