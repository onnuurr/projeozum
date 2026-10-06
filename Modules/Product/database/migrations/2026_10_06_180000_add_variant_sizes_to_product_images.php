<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bedene özel ürün görseli (2026-10-06): mağazada beden seçilince o yaştaki mankenin görselleri
 * gösterilir (Creative mokap — beden grubu başına set). variant_sizes: görselin ait olduğu beden
 * adları (product_variants.size, ör. ["14 Yaş","15 Yaş"]); NULL = genel görsel (ürün galerisi);
 * [] = hiçbir bedene ait değil (yerini yeni sete bıraktı, mağazaya gitmez).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_images', 'variant_sizes')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->json('variant_sizes')->nullable()->after('product_variant_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_images', 'variant_sizes')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropColumn('variant_sizes');
            });
        }
    }
};
