<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bütünsel (parça bazlı değil, tüm giysi görseli üzerinden) "bu ürünü ayırt
 * eden en fazla 5 görsel detay" analizi — bkz. ROADMAP.md Faz G.5,
 * GarmentIdentitySummarizerContract. Versioned zarf (analysis_version,
 * model, prompt_version, generated_at) + highlights[] dizisi taşır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_garment_scans', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_garment_scans', 'identity_summary')) {
                $table->json('identity_summary')->nullable()->after('detections');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_garment_scans', function (Blueprint $table) {
            if (Schema::hasColumn('creative_garment_scans', 'identity_summary')) {
                $table->dropColumn('identity_summary');
            }
        });
    }
};
