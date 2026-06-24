<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            // Renk-kodlu beden DXF katmanları (BEDEN_YESIL...). Filtre değil → JSON.
            $table->json('size_layers')->nullable()->after('size_range');
            // Renk(hex)→beden rolü kanıtı; gerçek beden adı operatörce atanır.
            $table->json('color_size_map')->nullable()->after('size_layers');
            // Maßtabelle best-effort matrisi {labels, matrix, unit, note}.
            $table->json('measurements')->nullable()->after('color_size_map');
            // Beden→kumaş tüketimi {"50/56":"30cm @150"}.
            $table->json('fabric_usage')->nullable()->after('measurements');
        });
    }

    public function down(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropColumn(['size_layers', 'color_size_map', 'measurements', 'fabric_usage']);
        });
    }
};
