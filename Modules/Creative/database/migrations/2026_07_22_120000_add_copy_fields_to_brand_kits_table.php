<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marka kitine "yazı" (copy) kimliği alanları ekler. Bu alanlar üretilen
 * görsellerin metin slotlarına (headline/sub/cta) yazılacak pazarlama metnini
 * markanın tek doğruluk kaynağından üretmek için CopyGeneratorContract'a beslenir:
 *
 *   - design_brief : markanın genel yaratıcı brief'i (serbest metin)
 *   - tone         : ton ipucu (ör. "sıcak, samimi, lüks")
 *   - cta_phrases  : tercih edilen harekete geçirici ifadeler (JSON dizi)
 *   - banned_words : üretilen metinde asla geçmemesi gereken kelimeler (JSON dizi)
 *
 * CLAUDE.md DB disiplini: ileri-tarihli, salt eklemeli (nullable) migration;
 * down() eklenen 4 kolonu birebir geri alır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->text('design_brief')->nullable()->after('spacing');
            $table->string('tone')->nullable()->after('design_brief');
            // Tercih edilen CTA ifadeleri: ["Hemen keşfet", "Sepete ekle", ...]
            $table->json('cta_phrases')->nullable()->after('tone');
            // Yasaklı kelimeler (marka güvenliği): ["ucuz", "indirim", ...]
            $table->json('banned_words')->nullable()->after('cta_phrases');
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            $table->dropColumn(['design_brief', 'tone', 'cta_phrases', 'banned_words']);
        });
    }
};
