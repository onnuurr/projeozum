<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            // Fotoğraftan çıkarım kaynağı (bkz. ExtractPatternFromPhotoJob) — PDF'e
            // ek, alternatif bir kaynak; pdf_path ile birlikte var olabilir.
            $table->string('photo_path')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
