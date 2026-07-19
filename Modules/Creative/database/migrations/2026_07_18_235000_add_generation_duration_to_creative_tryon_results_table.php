<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir giydirme üretiminin (manken poz compose + try-on + enhance) ne kadar
 * sürdüğünü milisaniye cinsinden saklar — hangi modelin/sürücünün ne kadar
 * yavaş/hızlı olduğunu raporlamak ve detay sayfasında göstermek için
 * (bkz. ProductOnModelService::generate, tryon_driver/tryon_model ile birlikte
 * okunur). Üretim başarısız olursa null kalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'generation_duration_ms')) {
                $table->unsignedInteger('generation_duration_ms')->nullable()->after('tryon_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'generation_duration_ms')) {
                $table->dropColumn('generation_duration_ms');
            }
        });
    }
};
