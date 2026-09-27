<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürünün tedarik kaynağı: 'purchased' (hazır alınan, mevcut "Yeni Ürün" akışı) veya
 * 'manufactured' (bizim ürettiğimiz; Atölye'de reçeteyle "geçici kart" — status
 * 'development' — olarak açılır, ilk iş emri tamamlanınca ürüne döner). Varsayılan
 * 'purchased' mevcut satırları da doldurur, ayrı backfill gerekmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sourcing')->default('purchased')->after('status');
            $table->index('sourcing');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sourcing']);
            $table->dropColumn('sourcing');
        });
    }
};
