<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aynı satır (product_id+pose_id benzersiz) üstünde çakışan üretimleri
 * ayırt etmek için: her queue() çağrısı yeni bir token üretir, job bitince
 * DB'deki token hâlâ kendi token'ıyla eşleşiyorsa sonucu yazar — eşleşmiyorsa
 * (daha yeni bir istek bu satırı geçersiz kılmış) sessizce atlar. Bkz.
 * ProductOnModelService::queue/generate, GenerateOnModelJob.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'generation_token')) {
                $table->string('generation_token', 36)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'generation_token')) {
                $table->dropColumn('generation_token');
            }
        });
    }
};
