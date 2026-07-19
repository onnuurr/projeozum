<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir parça ADININ (örnekten bağımsız) sabit korunma sınıfı ve taban önceliği
 * — "logo" her zaman identity/critical'dır, ürüne göre değişmez; bunu her
 * seferinde Gemini'ye sormak gereksiz LLM tutarsızlığı riski taşır (bkz.
 * ROADMAP.md Faz G.5, GarmentIdentityRuleEngine). Gemini'nin örnek-bazlı
 * (instance_priority) değerlendirmesi bu taban değerin ÜSTÜNE çıkabilir,
 * ALTINA asla düşemez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_garment_labels', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_garment_labels', 'preservation_category')) {
                // identity | appearance | construction | hardware
                $table->string('preservation_category', 20)->default('appearance')->after('group');
            }
            if (! Schema::hasColumn('creative_garment_labels', 'default_priority')) {
                // critical | high | medium | low
                $table->string('default_priority', 10)->default('medium')->after('preservation_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_garment_labels', function (Blueprint $table) {
            foreach (['preservation_category', 'default_priority'] as $col) {
                if (Schema::hasColumn('creative_garment_labels', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
