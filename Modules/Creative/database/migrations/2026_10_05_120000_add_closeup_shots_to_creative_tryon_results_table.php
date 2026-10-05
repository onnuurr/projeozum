<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yakın plan / detay çekimleri: onaylı bir tam boy giydirmeden (source_result_id) üretilen
 * yakın kadrajlar (bel üstü, etek ucu, kumaş dokusu...) aynı tabloda, aynı onay akışıyla
 * tutulur. `shot` = 'full' tam boy giydirme; diğer değerler config('creative.closeup.shots')
 * anahtarlarıdır. Tekillik (product_id, pose_id) → (product_id, pose_id, shot) genişler —
 * shot NOT NULL tutulur ki Postgres'te null'lar birbirinden farklı sayılıp tekillik delinmesin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'shot')) {
                $table->string('shot', 40)->default('full')->after('pose_id');
            }
            if (! Schema::hasColumn('creative_tryon_results', 'source_result_id')) {
                $table->foreignId('source_result_id')->nullable()->after('shot')
                    ->constrained('creative_tryon_results')->nullOnDelete();
            }
        });

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'pose_id']);
            $table->unique(['product_id', 'pose_id', 'shot']);
        });
    }

    public function down(): void
    {
        // Yakın plan satırları eski (product_id, pose_id) tekilliğini bozar — önce onlar silinir.
        \Illuminate\Support\Facades\DB::table('creative_tryon_results')->where('shot', '!=', 'full')->delete();

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'pose_id', 'shot']);
            $table->unique(['product_id', 'pose_id']);
        });

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'source_result_id')) {
                $table->dropConstrainedForeignId('source_result_id');
            }
            if (Schema::hasColumn('creative_tryon_results', 'shot')) {
                $table->dropColumn('shot');
            }
        });
    }
};
