<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * patterns.measurements (JSON {labels,sizes,matrix}) yerini normalize
 * pattern_measurements tablosuna bıraktı (2026-10-07). Kaldırıldığı anda canlı
 * DB'de dolu satır yoktu (0/1); veri taşıma gerekmedi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('patterns', 'measurements')) {
            Schema::table('patterns', function (Blueprint $table) {
                $table->dropColumn('measurements');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('patterns', 'measurements')) {
            Schema::table('patterns', function (Blueprint $table) {
                $table->json('measurements')->nullable()->after('color_size_map');
            });
        }
    }
};
