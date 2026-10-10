<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kalıp Kartı ölçü yapısı (2026-10-07):
 *  - pattern_measurement_points: ölçü noktası sözlüğü (vücut / hazır giysi)
 *  - pattern_types: kalıp tipleri (serbest metin product_type'ın yönetilebilir karşılığı)
 *  - pattern_type_measurement_points: tip başına ölçü şablonu ("her kategorinin kendi ölçüleri")
 *
 * Sözlük + başlangıç şablonları burada yazılır (referans veri; seeder'lar canlı DB'yi
 * geriye dönük düzeltmiyor). Mevcut patterns.product_type değerleri tip olarak eklenir.
 */
return new class extends Migration
{
    /** key => [ad, ipucu, basis] — vücut anahtarları PatternProfiles.vue MEASUREMENT_FIELDS ile aynı. */
    private const POINTS = [
        'height'          => ['Boy', 'Tepeden tabana toplam vücut boyu.', 'body'],
        'chest_circ'      => ['Göğüs Çevresi', 'Göğüs hizasından ölçülen tam çevre.', 'body'],
        'waist_circ'      => ['Bel Çevresi', 'Bel hizasından ölçülen tam çevre.', 'body'],
        'hip_circ'        => ['Kalça Çevresi', 'Kalçanın en geniş yerinden tam çevre.', 'body'],
        'shoulder_width'  => ['Omuz Genişliği', 'Bir omuz ucundan diğerine yatay mesafe.', 'body'],
        'neck_circ'       => ['Boyun Çevresi', 'Boyun kökünden ölçülen tam çevre.', 'body'],
        'back_width'      => ['Sırt Genişliği', 'Kürek kemikleri hizasında ölçülen sırt genişliği.', 'body'],
        'arm_length'      => ['Kol Uzunluğu', 'Omuzdan bilek ucuna kol uzunluğu.', 'body'],
        'armhole_depth'   => ['Kol Evi Derinliği', 'Omuzdan koltuk altına dikey derinlik.', 'body'],
        'upper_arm_circ'  => ['Pazı Çevresi', 'Üst kol (pazı) çevresi.', 'body'],
        'body_length'     => ['Beden Boyu', 'Omuz dikişinden etek ucuna gövde uzunluğu.', 'body'],
        'back_length'     => ['Sırt Boyu', 'Ense kökünden bele sırt ortası uzunluğu.', 'body'],
        'side_length'     => ['Yan Boy', 'Belden yere yan uzunluk.', 'body'],
        'inseam'          => ['İç Bacak Boyu', 'Ağdan yere iç bacak uzunluğu.', 'body'],
        'head_circ'       => ['Baş Çevresi', 'Alın hizasından baş çevresi.', 'body'],

        'g_length'           => ['Ürün Boyu', 'Omuz/yaka en üst noktasından etek ucuna.', 'garment'],
        'g_chest_half'       => ['Göğüs Eni (½)', 'Koltuk altının 2 cm altından, düz serili yarım en.', 'garment'],
        'g_waist_half'       => ['Bel Eni (½)', 'Bel hizasından düz serili yarım en.', 'garment'],
        'g_hip_half'         => ['Kalça Eni (½)', 'Kalça hizasından düz serili yarım en.', 'garment'],
        'g_hem_half'         => ['Etek Ucu Eni (½)', 'Etek ucundan düz serili yarım en.', 'garment'],
        'g_shoulder'         => ['Omuz Genişliği', 'Omuz dikişinden omuz dikişine.', 'garment'],
        'g_across_back'      => ['Sırt Genişliği', 'Arka kol evleri arası yatay.', 'garment'],
        'g_sleeve_length'    => ['Kol Boyu', 'Omuz dikişinden kol ucuna.', 'garment'],
        'g_armhole'          => ['Kol Evi (düz)', 'Omuz dikişinden koltuk altına düz.', 'garment'],
        'g_bicep_half'       => ['Pazı Eni (½)', 'Koltuk altının 2 cm altından kol yarım eni.', 'garment'],
        'g_cuff_half'        => ['Manşet Eni (½)', 'Kol ucu yarım eni.', 'garment'],
        'g_neck_width'       => ['Yaka Açıklığı', 'Yaka dikişinden yaka dikişine yatay.', 'garment'],
        'g_front_neck_drop'  => ['Ön Yaka Düşüklüğü', 'Omuz hattından ön yaka ortasına dikey.', 'garment'],
        'g_hood_height'      => ['Kapüşon Yüksekliği', 'Yaka dikişinden kapüşon tepesine.', 'garment'],
        'g_hood_width'       => ['Kapüşon Eni', 'Kapüşon yüz açıklığına dik en.', 'garment'],
        'g_rise_front'       => ['Ön Ağ', 'Bel ucundan ağ dikişine ön.', 'garment'],
        'g_rise_back'        => ['Arka Ağ', 'Bel ucundan ağ dikişine arka.', 'garment'],
        'g_inseam'           => ['İç Bacak', 'Ağ dikişinden paça ucuna.', 'garment'],
        'g_outseam'          => ['Dış Boy', 'Bel ucundan paça ucuna yan dikiş boyunca.', 'garment'],
        'g_thigh_half'       => ['Uyluk Eni (½)', 'Ağın 2 cm altından paça yarım eni.', 'garment'],
        'g_leg_opening_half' => ['Paça Eni (½)', 'Paça ucu yarım eni.', 'garment'],
        'g_waistband_height' => ['Bel Bandı Yüksekliği', 'Bel bandı/lastik yüksekliği.', 'garment'],
    ];

    private const TOP_BODY = ['height', 'chest_circ', 'waist_circ', 'hip_circ', 'arm_length', 'back_length'];
    private const TOP_GARMENT = ['g_length', 'g_chest_half', 'g_hem_half', 'g_shoulder', 'g_sleeve_length', 'g_armhole', 'g_cuff_half', 'g_neck_width'];
    private const HOOD = ['g_hood_height', 'g_hood_width'];
    private const BOTTOM_BODY = ['height', 'waist_circ', 'hip_circ', 'side_length', 'inseam'];
    private const BOTTOM_GARMENT = ['g_waist_half', 'g_hip_half', 'g_rise_front', 'g_rise_back', 'g_inseam', 'g_outseam', 'g_thigh_half', 'g_leg_opening_half', 'g_waistband_height'];

    /** @return array<string,array<int,string>>  tip adı => şablon nokta anahtarları (sıralı) */
    private function templates(): array
    {
        $top = [...self::TOP_BODY, ...self::TOP_GARMENT];
        $hooded = [...$top, ...self::HOOD];
        $bottom = [...self::BOTTOM_BODY, ...self::BOTTOM_GARMENT];

        return [
            'sweatshirt' => $top,
            'kapüşonlu'  => $hooded,
            'anorak'     => $hooded,
            'tişört'     => $top,
            'gömlek'     => $top,
            'üst'        => $top,
            'ceket'      => $top,
            'palto'      => $hooded,
            'pantolon'   => $bottom,
            'şort'       => $bottom,
            'etek'       => ['height', 'waist_circ', 'hip_circ', 'g_waist_half', 'g_hip_half', 'g_length', 'g_hem_half', 'g_waistband_height'],
            'elbise'     => [...self::TOP_BODY, 'g_length', 'g_chest_half', 'g_waist_half', 'g_hem_half', 'g_shoulder', 'g_sleeve_length', 'g_armhole'],
            'tulum'      => ['height', 'chest_circ', 'waist_circ', 'hip_circ', 'inseam', 'g_length', 'g_chest_half', 'g_waist_half', 'g_hip_half', 'g_inseam', 'g_leg_opening_half'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('pattern_measurement_points')) {
            Schema::create('pattern_measurement_points', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('name', 100);
                $table->string('hint')->nullable();
                // 'body' = vücut ölçüsü (PDF beden tabloları), 'garment' = hazır giysi ölçüsü (üretim kontrolü)
                $table->string('basis', 16)->default('garment');
                $table->string('unit', 8)->default('cm');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['basis', 'sort_order']);
            });
        }

        if (! Schema::hasTable('pattern_types')) {
            Schema::create('pattern_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pattern_type_measurement_points')) {
            Schema::create('pattern_type_measurement_points', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pattern_type_id')->constrained('pattern_types')->cascadeOnDelete();
                $table->foreignId('measurement_point_id')->constrained('pattern_measurement_points')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);

                $table->unique(['pattern_type_id', 'measurement_point_id'], 'ptmp_type_point_unique');
            });
        }

        $now = now();
        $sort = 0;
        foreach (self::POINTS as $key => [$name, $hint, $basis]) {
            DB::table('pattern_measurement_points')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'hint' => $hint, 'basis' => $basis, 'unit' => 'cm',
                    'sort_order' => $sort += 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
        $pointIds = DB::table('pattern_measurement_points')->pluck('id', 'key');

        $templates = $this->templates();
        $existing = Schema::hasTable('patterns')
            ? DB::table('patterns')->whereNotNull('product_type')->where('product_type', '!=', 'belirsiz')
                ->distinct()->pluck('product_type')->all()
            : [];
        foreach ($existing as $name) {
            $templates[$name] ??= [];
        }

        $typeSort = 0;
        foreach ($templates as $name => $keys) {
            DB::table('pattern_types')->updateOrInsert(
                ['name' => $name],
                ['sort_order' => $typeSort += 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $typeId = DB::table('pattern_types')->where('name', $name)->value('id');
            foreach (array_values($keys) as $i => $key) {
                DB::table('pattern_type_measurement_points')->updateOrInsert(
                    ['pattern_type_id' => $typeId, 'measurement_point_id' => $pointIds[$key]],
                    ['sort_order' => ($i + 1) * 10],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_type_measurement_points');
        Schema::dropIfExists('pattern_types');
        Schema::dropIfExists('pattern_measurement_points');
    }
};
