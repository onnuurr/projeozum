<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün formundaki beden/renk seçenekleri önceden ProductForm.vue içinde sabit diziydi.
 * Artık Product modülünde (variant-option.* yetkileriyle) yönetilen iki referans tablosu.
 * Varyantlar (product_variants.size / color_name / color_hex) düz metin olarak kalır —
 * bu tablolar yalnızca seçim listesidir, varyantlara FK yoktur.
 * Önceki sabit liste aynen tohumlanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('hex', 7);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        $now = now();

        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', '40', '42', '44'];
        DB::table('product_sizes')->insert(array_map(fn ($name, $i) => [
            'name' => $name, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
        ], $sizes, array_keys($sizes)));

        $colors = [
            ['Siyah', '#111111'], ['Beyaz', '#fafafa'], ['Lacivert', '#1e3a8a'], ['Gri', '#6b7280'],
            ['Bej', '#d6c7a8'], ['Bordo', '#7c1f1f'], ['Yeşil', '#15803d'], ['Mavi', '#2563eb'],
            ['Kırmızı', '#dc2626'], ['Sarı', '#facc15'], ['Pembe', '#ec4899'], ['Mor', '#7c3aed'],
        ];
        DB::table('product_colors')->insert(array_map(fn ($c, $i) => [
            'name' => $c[0], 'hex' => $c[1], 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
        ], $colors, array_keys($colors)));
    }

    public function down(): void
    {
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('product_sizes');
    }
};
