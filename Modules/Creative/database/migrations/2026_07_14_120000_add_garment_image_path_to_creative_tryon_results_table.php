<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün giydirmede, ürünün mevcut fotoğrafı yerine (veya hiç fotoğrafı yokken)
 * ekranda ayrıca yüklenen giysi görselinin göreli yolunu tutar. Bu görsel
 * yalnızca üretim (generate) için kullanılır; ürünün kalıcı product_images
 * galerisine eklenmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'garment_image_path')) {
                $table->string('garment_image_path', 500)->nullable()->after('pose_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'garment_image_path')) {
                $table->dropColumn('garment_image_path');
            }
        });
    }
};
