<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giydirme sonuçlarına hat ayrımı: legacy (manken her seferinde pozlanır) | mockup (kilitli baz +
 * maske + birleştirme). Mokap sonuçları aynı onay/yayın akışını kullanır; mevcut (legacy)
 * satırları ezmemeleri için tekillik (product_id, pose_id, shot, pipeline) olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'pipeline')) {
                $table->string('pipeline', 10)->default('legacy')->after('shot');
            }
            if (! Schema::hasColumn('creative_tryon_results', 'mockup_base_id')) {
                $table->foreignId('mockup_base_id')->nullable()->after('pipeline')
                    ->constrained('creative_mockup_bases')->nullOnDelete();
            }
            if (! Schema::hasColumn('creative_tryon_results', 'qa')) {
                $table->json('qa')->nullable()->after('meta');
            }
        });

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'pose_id', 'shot']);
            $table->unique(['product_id', 'pose_id', 'shot', 'pipeline']);
        });
    }

    public function down(): void
    {
        // Mokap satırları eski tekilliği bozabilir — önce onlar silinir.
        \Illuminate\Support\Facades\DB::table('creative_tryon_results')->where('pipeline', '!=', 'legacy')->delete();

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'pose_id', 'shot', 'pipeline']);
            $table->unique(['product_id', 'pose_id', 'shot']);
        });

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'qa')) {
                $table->dropColumn('qa');
            }
            if (Schema::hasColumn('creative_tryon_results', 'mockup_base_id')) {
                $table->dropConstrainedForeignId('mockup_base_id');
            }
            if (Schema::hasColumn('creative_tryon_results', 'pipeline')) {
                $table->dropColumn('pipeline');
            }
        });
    }
};
