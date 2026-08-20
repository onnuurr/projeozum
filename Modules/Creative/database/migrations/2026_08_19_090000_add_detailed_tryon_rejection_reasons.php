<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Model Giydirme (tryon) ret ekranı için detaylandırılmış madde seti.
 *
 * Şimdiye dek tek genel "Giysi uyumu" maddesi tüm giydirme kusurlarını
 * (kol, yaka, fermuar, kumaş, baskı, ölçek...) tek etikette topluyordu — hem
 * ReviewChatService'e giden düzeltme talimatını belirsizleştiriyor hem de
 * creative:review-report'taki tag_counts kırılımını köreltiyor (bkz. haftalık
 * ret raporundaki en yüksek etiket her zaman "Giysi uyumu" çıkıyordu).
 *
 * Bu maddeler yalnız context='tryon' ile eklenir — Creative Galerisi/Manken
 * ekranlarında görünmezler. Mevcut genel maddeler (id 1-11) DOKUNULMADAN
 * kalır; geçmiş review_tags snapshot'ları bozulmasın diye hiçbir etiket
 * yeniden adlandırılmadı, sadece yenileri eklendi.
 */
return new class extends Migration
{
    private const CATEGORY = 'Düzeltilmesi gereken alan';

    private const CONTEXT = 'tryon';

    /** @return array<int,array{label:string,hint:string}> */
    private function reasons(): array
    {
        return [
            ['label' => 'Kol boyu/kesimi', 'hint' => 'sleeve length or cut mismatch versus garment reference'],
            ['label' => 'Yaka/kapüşon detayı', 'hint' => 'collar or hood shape and fit mismatch'],
            ['label' => 'Fermuar/düğme/çıtçıt', 'hint' => 'zipper, button or snap closure missing or misplaced'],
            ['label' => 'Kumaş dökümü / dokusu', 'hint' => 'fabric drape or texture does not match reference material'],
            ['label' => 'Baskı / desen hizası', 'hint' => 'print or pattern placement, scale or alignment mismatch'],
            ['label' => 'Boy / kesim uyumu', 'hint' => 'garment length or cut proportion mismatch, e.g. cropped vs regular'],
            ['label' => 'Aksesuar eksik/fazla', 'hint' => 'garment accessory such as drawstring, patch or logo missing or wrongly added'],
            ['label' => 'Renk uyumu', 'hint' => 'garment color does not match reference photo'],
            ['label' => 'Dikiş / kenar detayı', 'hint' => 'stitching, hem or seam detail mismatch'],
            ['label' => 'Giysi-vücut ölçek uyumu', 'hint' => 'garment appears scaled too large or small relative to model body'],
        ];
    }

    public function up(): void
    {
        $now = now();
        $nextSort = (int) DB::table('creative_rejection_reasons')->max('sort_order') + 1;

        foreach ($this->reasons() as $reason) {
            $exists = DB::table('creative_rejection_reasons')
                ->where('label', $reason['label'])
                ->where('context', self::CONTEXT)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('creative_rejection_reasons')->insert([
                'category'   => self::CATEGORY,
                'context'    => self::CONTEXT,
                'label'      => $reason['label'],
                'hint'       => $reason['hint'],
                'sort_order' => $nextSort++,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('creative_rejection_reasons')
            ->where('context', self::CONTEXT)
            ->whereIn('label', array_column($this->reasons(), 'label'))
            ->delete();
    }
};
