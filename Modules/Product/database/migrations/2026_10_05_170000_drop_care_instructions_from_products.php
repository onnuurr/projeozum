<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün formundaki serbest metin "Yıkama / Bakım Talimatları" kaldırıldı (2026-10-05): yıkama
 * talimatı Ürün Ayarları → Özellikler'deki kategori özelliği olarak (ikonlu seçimler) giriliyor,
 * ikisi yan yana duruyordu. Kaldırıldığında hiçbir üründe değer yoktu. Bagisto tarafındaki
 * karşılığı `bakim_talimati` attribute'u SaasSync paketinin migration'ıyla silinir.
 *
 * down(): kolonu boş olarak geri açar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('care_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('care_instructions')->nullable();
        });
    }
};
