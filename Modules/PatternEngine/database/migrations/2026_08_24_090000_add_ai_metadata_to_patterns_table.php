<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            // Ham AI çıkarım zarfı (versioned: analysis_version/model/prompt_version/
            // generated_at/data) — bkz. PatternMetadataExtractorContract. Gerçek
            // product_type/size_range kolonlarına ASLA doğrudan yazılmaz; aradaki
            // PatternMetadataRuleEngine confidence eşiğini geçen alanları uygular.
            // Ham zarf burada, düşük-güvenli öneriler dahil, her zaman saklanır.
            $table->json('ai_metadata')->nullable()->after('fabric_usage');
        });
    }

    public function down(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropColumn('ai_metadata');
        });
    }
};
