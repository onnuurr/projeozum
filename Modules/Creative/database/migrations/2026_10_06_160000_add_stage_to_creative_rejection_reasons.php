<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap Faz 5: ret nedeni ↔ mokap aşaması (base | stylist | mask | tryon | composite). Reddedilen mokap
 * sonucu nedeninin aşamasından yeniden üretilebilir (MockupRerunService) ve günlük rapor aşama
 * dağılımını gösterir. Yüz/saç/ışık/duruş/arka plan → baz (manken + kombin görseli); ürün nedenleri →
 * giydirme. Mokapa özgü yeni nedenler eklenir. Seeder canlı DB'yi kendiliğinden güncellemediği için burada.
 */
return new class extends Migration
{
    private const STAGES = [
        'base'  => ['Göz', 'Burun', 'Ağız / Gülüş', 'Saç', 'Ten / Cilt', 'Vücut oranı', 'Işık', 'Duruş / Poz', 'Arka plan'],
        'tryon' => ['Giysi uyumu', 'Giysi ön/arka karışıklığı', 'Kol boyu/kesimi', 'Yaka/kapüşon detayı', 'Fermuar/düğme/çıtçıt',
            'Kumaş dökümü / dokusu', 'Baskı / desen hizası', 'Boy / kesim uyumu', 'Aksesuar eksik/fazla', 'Renk uyumu',
            'Dikiş / kenar detayı', 'Giysi-vücut ölçek uyumu'],
    ];

    /** [label, hint (İngilizce, düzeltme talimatına girer), stage] — yalnız giydirme ekranı */
    private const NEW = [
        ['Üründe olmayan detay eklenmiş', 'a detail that is not on the product was added (extra seam, pocket, tier or print)', 'tryon'],
        ['Birleştirme izi / kenar', 'visible compositing seam, halo or hard edge around the hands, arms or hair', 'composite'],
        ['El veya ayakkabı bozuk', 'hands or shoes look distorted or broken', 'composite'],
        ['Kombin ürüne uymuyor', 'the outfit pieces do not suit the product (style, color or formality)', 'stylist'],
        ['Ürün tipi / boy yanlış algılanmış', 'the garment type or length was detected wrongly (e.g. maxi shown as midi)', 'stylist'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('creative_rejection_reasons', 'stage')) {
            Schema::table('creative_rejection_reasons', function (Blueprint $table) {
                $table->string('stage', 20)->nullable()->after('context');
            });
        }

        foreach (self::STAGES as $stage => $labels) {
            DB::table('creative_rejection_reasons')->whereIn('label', $labels)->update(['stage' => $stage]);
        }

        $order = (int) DB::table('creative_rejection_reasons')->max('sort_order');
        $now = now();
        foreach (self::NEW as [$label, $hint, $stage]) {
            if (DB::table('creative_rejection_reasons')->where('label', $label)->exists()) {
                continue;
            }
            DB::table('creative_rejection_reasons')->insert([
                'category' => 'Düzeltilmesi gereken alan', 'context' => 'tryon', 'label' => $label, 'hint' => $hint,
                'stage' => $stage, 'sort_order' => ++$order, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('creative_rejection_reasons')->whereIn('label', array_column(self::NEW, 0))->delete();

        if (Schema::hasColumn('creative_rejection_reasons', 'stage')) {
            Schema::table('creative_rejection_reasons', function (Blueprint $table) {
                $table->dropColumn('stage');
            });
        }
    }
};
