<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Tekrarlayan bir hata modu için özel ret maddesi: giysinin arka baskısı/etiketi
 * mankenin yakasında/ön gövdesinde görünmesi (bkz. GeminiTryOnPromptBuilder'daki
 * ilgili düzeltme). Bu madde işaretlendiğinde ReviewChatService aynı talimatı
 * otomatik yeniden üretim promptuna ekler; ayrıca creative:review-report bu
 * etiketin sıklığını izler.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('creative_rejection_reasons')
            ->where('label', 'Giysi ön/arka karışıklığı')
            ->exists();

        if (! $exists) {
            $now = now();
            $maxSort = (int) DB::table('creative_rejection_reasons')->max('sort_order');

            DB::table('creative_rejection_reasons')->insert([
                'category'   => 'Düzeltilmesi gereken alan',
                'label'      => 'Giysi ön/arka karışıklığı',
                'hint'       => 'garment back print, tag or lining showing on the front/collar area',
                'sort_order' => $maxSort + 1,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('creative_rejection_reasons')
            ->where('label', 'Giysi ön/arka karışıklığı')
            ->delete();
    }
};
