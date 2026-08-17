<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * creative_tryon_results.generation_token ile aynı korumayı creative_assets'e
 * getirir: regenerate()/applyAsset() her seferinde yeni bir token üretir, job
 * bitince DB'deki token hâlâ kendi token'ıyla eşleşiyorsa sonucu yazar —
 * eşleşmiyorsa (daha yeni bir istek bu satırı geçersiz kılmış) sessizce atlar.
 * Bkz. CreativeRenderService::generate, GenerateCreativeJob.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_assets', 'generation_token')) {
                $table->string('generation_token', 36)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_assets', function (Blueprint $table) {
            if (Schema::hasColumn('creative_assets', 'generation_token')) {
                $table->dropColumn('generation_token');
            }
        });
    }
};
