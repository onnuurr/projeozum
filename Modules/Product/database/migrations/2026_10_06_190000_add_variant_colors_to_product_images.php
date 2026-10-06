<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renge özel ürün görseli (2026-10-06): variant_colors = görselin ait olduğu renk adları
 * (product_variants.color_name); NULL = bütün renkler. Yalın ürün fotoğrafı bir renge etiketlenir
 * (Creative mokap o rengi bu fotoğraftan giydirir); onaylanan mokap görseli rengi + bedenleriyle
 * yalnız o varyantlara gider (Bagisto). variant_sizes ile birlikte değerlendirilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_images', 'variant_colors')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->json('variant_colors')->nullable()->after('variant_sizes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_images', 'variant_colors')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropColumn('variant_colors');
            });
        }
    }
};
