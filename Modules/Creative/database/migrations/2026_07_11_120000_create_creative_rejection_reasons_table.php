<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ret açıklamasının yanına eklenen "düzeltilmesi gereken alan" seçim maddeleri.
 *
 * Superadmin panelinden yönetilir (ekle/çıkar). Reddedilen manken/tryon görselinde
 * yönetici bir açıklama yazmak yerine (veya yanında) hazır maddeleri işaretler;
 * bu maddeler ReviewChatService prompt'una otomatik verilir, kullanıcı aynı şeyi
 * tekrar tekrar yazmak zorunda kalmaz.
 *
 * SoftDeletes: madde "çıkarıldığında" geçmiş retlerdeki seçimler (label snapshot)
 * anlamlı kalsın ve işlem geri alınabilir olsun diye kullanılır (CLAUDE.md md.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_rejection_reasons', function (Blueprint $table) {
            $table->id();
            // Maddelerin gruplandığı başlık (örn. "Düzeltilmesi gereken alan").
            $table->string('category', 120)->default('Düzeltilmesi gereken alan');
            // Görünen etiket (örn. Göz, Burun, Gülüş).
            $table->string('label', 120);
            // AI'a maddenin ne demek olduğunu netleştirmek için opsiyonel ipucu.
            $table->string('hint', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        // Özelliğin kutudan çıkar çıkmaz kullanılabilir olması için örnek maddeler.
        // Idempotent: tabloda kayıt varsa dokunma.
        if (DB::table('creative_rejection_reasons')->count() === 0) {
            $now  = now();
            $area = 'Düzeltilmesi gereken alan';
            $rows = [
                ['label' => 'Göz',        'hint' => 'eyes shape, symmetry or gaze'],
                ['label' => 'Burun',      'hint' => 'nose shape or proportion'],
                ['label' => 'Ağız / Gülüş', 'hint' => 'mouth or smile expression'],
                ['label' => 'Saç',        'hint' => 'hair style, length or color'],
                ['label' => 'Ten / Cilt', 'hint' => 'skin tone or texture'],
                ['label' => 'Vücut oranı', 'hint' => 'body proportions or measurements'],
                ['label' => 'Işık',       'hint' => 'lighting, exposure or shadows'],
                ['label' => 'Duruş / Poz', 'hint' => 'pose or posture'],
                ['label' => 'Giysi uyumu', 'hint' => 'garment fit and try-on accuracy'],
                ['label' => 'Arka plan',  'hint' => 'background or scene'],
            ];

            $insert = [];
            foreach ($rows as $i => $row) {
                $insert[] = [
                    'category'   => $area,
                    'label'      => $row['label'],
                    'hint'       => $row['hint'],
                    'sort_order' => $i,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('creative_rejection_reasons')->insert($insert);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_rejection_reasons');
    }
};
