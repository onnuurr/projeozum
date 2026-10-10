<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Özellik seçeneğine isteğe bağlı ikon (ortak ikon kaydındaki ad, örn. 'oz:bakim-yikama-30').
 * Yıkama talimatı sembolleri gibi görsel değerler için; özel (oz:) ikonlar Bagisto'ya seçenek
 * görseli olarak da gider (PushAttributeOptionToBagisto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attribute_options', function (Blueprint $table) {
            $table->string('icon', 64)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_attribute_options', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
