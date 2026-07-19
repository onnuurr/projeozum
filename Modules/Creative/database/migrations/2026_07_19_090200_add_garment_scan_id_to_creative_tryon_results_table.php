<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir giydirme üretiminin hangi giysi parça taramasından (creative_garment_scans)
 * beslendiğini izler — detay sayfasında/raporlarda üretimi kendi taramasına
 * geri bağlamak için (bkz. ProductOnModelService::generate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'garment_scan_id')) {
                $table->foreignId('garment_scan_id')
                    ->nullable()
                    ->after('garment_image_path')
                    ->constrained('creative_garment_scans')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'garment_scan_id')) {
                $table->dropConstrainedForeignId('garment_scan_id');
            }
        });
    }
};
