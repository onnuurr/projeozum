<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\TrainingIngest\Database\Migrations\Concerns\SkipsNonPostgres;

/**
 * Seed, creative_rejection_reasons'ın 2026-08-19 tarihli canlı-DB anlık görüntüsüdür
 * (kullanıcı kararı: training tarafı, orijinal spec'in İngilizce ML kodları yerine
 * prod'daki gerçek Türkçe taksonomiyi birebir yansıtır — bkz. plan bölüm 2).
 * Prod'da yeni bir ret sebebi eklenirse buraya AYRI bir migration ile eklenir,
 * bu dosya sonradan değiştirilmez (CLAUDE.md: prod'a gitmiş migration düzenlenmez).
 */
return new class extends Migration
{
    use SkipsNonPostgres;

    protected $connection = 'training';

    public function up(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->create('rejection_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('code')->unique();
            $table->text('label');
            $table->text('description')->nullable();
            $table->text('category')->nullable();
            $table->text('context')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });

        $now = now();
        $category = 'Düzeltilmesi gereken alan';

        $rows = [
            ['GOZ', 'Göz', 'eyes shape, symmetry or gaze', 0],
            ['BURUN', 'Burun', 'nose shape or proportion', 1],
            ['AGIZ_GULUS', 'Ağız / Gülüş', 'mouth or smile expression', 2],
            ['SAC', 'Saç', 'hair style, length or color', 3],
            ['TEN_CILT', 'Ten / Cilt', 'skin tone or texture', 4],
            ['VUCUT_ORANI', 'Vücut oranı', 'body proportions or measurements', 5],
            ['ISIK', 'Işık', 'lighting, exposure or shadows', 6],
            ['DURUS_POZ', 'Duruş / Poz', 'pose or posture', 7],
            ['GIYSI_UYUMU', 'Giysi uyumu', 'garment fit and try-on accuracy', 8],
            ['ARKA_PLAN', 'Arka plan', 'background or scene', 9],
            ['GIYSI_ON_ARKA_KARISIKLIGI', 'Giysi ön/arka karışıklığı', 'garment back print, tag or lining showing on the front/collar area', 10],
        ];

        foreach ($rows as [$code, $label, $description, $sortOrder]) {
            DB::connection($this->connection)->table('rejection_reasons')->insert([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'label' => $label,
                'description' => $description,
                'category' => $category,
                'context' => null,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => $now,
            ]);
        }

        // Serbest metinle reddedilip prod taksonomisindeki hiçbir etikete uymayan
        // vakalar için düşüş anahtarı (spec'teki OTHER'ın karşılığı).
        DB::connection($this->connection)->table('rejection_reasons')->insert([
            'id' => (string) Str::uuid(),
            'code' => 'OTHER',
            'label' => 'Diğer',
            'description' => 'Yukarıdaki taksonomiye uymayan, yalnızca serbest metinle açıklanmış ret',
            'category' => $category,
            'context' => null,
            'sort_order' => 99,
            'is_active' => true,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('rejection_reasons');
    }
};
