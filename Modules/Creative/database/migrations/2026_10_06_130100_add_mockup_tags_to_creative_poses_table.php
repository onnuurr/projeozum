<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap poz etiketleri (Faz 3; önceden config creative.mockup.excluded_poses / pose_views):
 *  - mockup_regions: pozun kullanılabileceği ürün bölgeleri; null/boş = mokapta kullanılmaz
 *    (maske kalitesi ölçülmemiş ya da ürünü gizleyen pozlar: oturma, çömelme, kollar kavuşmuş...)
 *  - requires: ürünün sahip olması gereken özellikler (ör. "pockets" — el cepte pozu)
 *  - view: front|side|back|three_quarter — sadakat kontrolü görünmeyen detayı eksik saymasın
 */
return new class extends Migration
{
    private const ALL = ['bottom', 'top', 'one_piece', 'set'];

    /** pose_key => [mockup_regions, requires, view]; listede olmayan poz: bütün bölgeler, gereksinim yok, front */
    private const TAGS = [
        'urun_detay'            => [null, null, 'front'],
        'seated_chair'          => [null, null, 'front'],
        'seated_editorial'      => [null, null, 'front'],
        'seated_floor'          => [null, null, 'front'],
        'crouching'             => [null, null, 'front'],
        'arms_crossed'          => [null, null, 'front'],
        'arms_raised'           => [null, null, 'front'],
        'leaning_wall'          => [null, null, 'side'],
        'hands_in_pockets'      => [null, ['pockets'], 'front'],
        'standing_back'         => [null, null, 'back'],
        'three_qtr_back'        => [null, null, 'back'],
        '1_el_cepte'            => [self::ALL, ['pockets'], 'front'],
        'adjusting_collar'      => [self::ALL, ['collar'], 'front'],
        'standing_profile'      => [self::ALL, null, 'side'],
        'looking_over_shoulder' => [self::ALL, null, 'back'],
        'standing_three_qtr'    => [self::ALL, null, 'three_quarter'],
        'walking_stride'        => [self::ALL, null, 'side'],
        'twisting_torso'        => [self::ALL, null, 'three_quarter'],
        'contrapposto'          => [self::ALL, null, 'three_quarter'],
    ];

    public function up(): void
    {
        Schema::table('creative_poses', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_poses', 'mockup_regions')) {
                $table->json('mockup_regions')->nullable();
            }
            if (! Schema::hasColumn('creative_poses', 'requires')) {
                $table->json('requires')->nullable();
            }
            if (! Schema::hasColumn('creative_poses', 'view')) {
                $table->string('view', 20)->default('front');
            }
        });

        foreach (DB::table('creative_poses')->pluck('pose_key', 'id') as $id => $key) {
            [$regions, $requires, $view] = self::TAGS[$key] ?? [self::ALL, null, 'front'];
            DB::table('creative_poses')->where('id', $id)->update([
                'mockup_regions' => $regions === null ? null : json_encode($regions),
                'requires'       => $requires === null ? null : json_encode($requires),
                'view'           => $view,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('creative_poses', function (Blueprint $table) {
            foreach (['mockup_regions', 'requires', 'view'] as $col) {
                if (Schema::hasColumn('creative_poses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
