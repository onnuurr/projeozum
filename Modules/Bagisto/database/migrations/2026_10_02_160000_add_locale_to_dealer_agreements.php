<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi sözleşmelerine dil: her sözleşmenin Türkçe ve İngilizce sürümleri ayrı yayımlanır, sürüm
 * numarası dil içinde artar (benzersizlik sözleşme + dil + sürüm). Mevcut tüm sürümler Türkçedir.
 * Onay arşivi de hangi dildeki metnin onaylandığını saklar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->string('locale', 5)->default('tr')->after('dealer_agreement_id');
        });

        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->dropUnique(['dealer_agreement_id', 'version']);
            $table->dropIndex(['dealer_agreement_id', 'published_at']);
            $table->unique(['dealer_agreement_id', 'locale', 'version']);
            $table->index(['dealer_agreement_id', 'locale', 'published_at']);
        });

        Schema::table('dealer_agreement_acceptances', function (Blueprint $table) {
            $table->string('locale', 5)->default('tr')->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('dealer_agreement_acceptances', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->dropUnique(['dealer_agreement_id', 'locale', 'version']);
            $table->dropIndex(['dealer_agreement_id', 'locale', 'published_at']);
        });

        // İngilizce sürümler varken eski (sözleşme + sürüm) benzersizliği kurulamaz; geri alınırken atılır.
        \Illuminate\Support\Facades\DB::table('dealer_agreement_versions')->where('locale', '!=', 'tr')->delete();

        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->dropColumn('locale');
            $table->unique(['dealer_agreement_id', 'version']);
            $table->index(['dealer_agreement_id', 'published_at']);
        });
    }
};
