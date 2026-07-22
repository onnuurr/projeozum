<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operatörün manken oluşturma formunda opsiyonel yüklediği referans fotoğraf
 * (gerçekçilik/ışık-doku çapası — bkz. MannequinPromptBuilder). Üretilen çıktı
 * olan reference_image_path ile karışmaması için ayrı kolon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_mannequins', function (Blueprint $table) {
            $table->string('source_photo_path')->nullable()->after('extras');
        });
    }

    public function down(): void
    {
        Schema::table('creative_mannequins', function (Blueprint $table) {
            $table->dropColumn('source_photo_path');
        });
    }
};
