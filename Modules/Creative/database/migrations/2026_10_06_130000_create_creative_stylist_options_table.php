<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap stilistinin seçenek listesi (Faz 3): stilist serbest metin yazmaz, bu listeden anahtar seçer;
 * kombin cümlesini MockupStylistRuleEngine seçeneklerin prompt_en alanlarından kurar.
 *
 * tags: regions (hangi ürün bölgesinin yanında kullanılabilir), genders (girl|boy; boş = ikisi),
 * lengths (ürün boyu; boş = hepsi), style (casual|sporty|dressy|romantic), season (warm|cool; boş = her mevsim),
 * colorable (renk seçeneği uygulanır mı), placement (aksesuar: hair|wrist|chest).
 * Başlangıç listesi burada (seeder canlı DB'yi kendiliğinden güncellemez); düzenleme Faz 4 ayar sayfasında.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creative_stylist_options')) {
            return;
        }

        Schema::create('creative_stylist_options', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('key', 60);
            $table->string('label_tr', 120);
            $table->string('prompt_en', 255)->default('');
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kind', 'key']);
        });

        $both = ['girl', 'boy'];
        $notTop = ['bottom'];                       // üst parçası yalnız alt ürünün yanına
        $notBottom = ['top'];                       // alt parçası yalnız üst ürünün yanına
        $all = ['bottom', 'top', 'one_piece', 'set'];
        $short = ['mini', 'knee'];

        $rows = [
            // Üst (alt ürünün yanına)
            ['top', 'fitted_rib_tee', 'Dar kesim fitilli tişört', 'a fitted short-sleeve ribbed cotton t-shirt', ['regions' => $notTop, 'genders' => $both, 'style' => ['casual', 'romantic'], 'colorable' => true]],
            ['top', 'crew_tee', 'Bisiklet yaka tişört', 'a plain short-sleeve crew-neck cotton t-shirt', ['regions' => $notTop, 'genders' => $both, 'style' => ['casual', 'sporty'], 'colorable' => true]],
            ['top', 'puff_sleeve_blouse', 'Balon kollu bluz', 'a short puff-sleeve cotton blouse', ['regions' => $notTop, 'genders' => ['girl'], 'style' => ['romantic', 'dressy'], 'colorable' => true]],
            ['top', 'knit_polo', 'Triko polo', 'a short-sleeve fine-knit polo shirt', ['regions' => $notTop, 'genders' => $both, 'style' => ['dressy', 'casual'], 'colorable' => true]],
            ['top', 'button_shirt', 'Kısa kollu gömlek', 'a short-sleeve cotton button-up shirt', ['regions' => $notTop, 'genders' => $both, 'style' => ['dressy'], 'colorable' => true]],
            ['top', 'tank_top', 'Fitilli atlet', 'a ribbed cotton tank top', ['regions' => $notTop, 'genders' => ['girl'], 'style' => ['casual', 'sporty'], 'season' => ['warm'], 'colorable' => true]],
            ['top', 'long_sleeve_tee', 'Uzun kollu tişört', 'a fitted long-sleeve cotton t-shirt', ['regions' => $notTop, 'genders' => $both, 'style' => ['casual'], 'season' => ['cool'], 'colorable' => true]],
            ['top', 'fine_knit_sweater', 'İnce triko kazak', 'a fine-knit crew-neck sweater', ['regions' => $notTop, 'genders' => $both, 'style' => ['casual', 'dressy'], 'season' => ['cool'], 'colorable' => true]],
            // Alt (üst ürünün yanına)
            ['bottom', 'straight_jeans', 'Düz paça kot', 'mid-blue straight-leg jeans', ['regions' => $notBottom, 'genders' => $both, 'style' => ['casual']]],
            ['bottom', 'denim_shorts', 'Kot şort', 'light-blue denim shorts', ['regions' => $notBottom, 'genders' => $both, 'style' => ['casual', 'sporty'], 'season' => ['warm']]],
            ['bottom', 'chino_trousers', 'Kumaş (chino) pantolon', 'cotton chino trousers', ['regions' => $notBottom, 'genders' => $both, 'style' => ['dressy'], 'colorable' => true]],
            ['bottom', 'jogger_pants', 'Eşofman altı', 'soft cotton jogger pants', ['regions' => $notBottom, 'genders' => $both, 'style' => ['sporty'], 'colorable' => true]],
            ['bottom', 'pleated_skirt', 'Pileli etek', 'a knee-length pleated skirt', ['regions' => $notBottom, 'genders' => ['girl'], 'style' => ['dressy', 'romantic'], 'colorable' => true]],
            ['bottom', 'leggings', 'Tayt', 'fitted cotton leggings', ['regions' => $notBottom, 'genders' => ['girl'], 'style' => ['casual', 'sporty'], 'colorable' => true]],
            // Ayakkabı
            ['footwear', 'canvas_sneakers', 'Kanvas spor ayakkabı', 'low-top canvas sneakers', ['regions' => $all, 'genders' => $both, 'style' => ['casual', 'sporty']]],
            ['footwear', 'leather_sneakers', 'Deri spor ayakkabı', 'clean white leather sneakers', ['regions' => $all, 'genders' => $both, 'style' => ['casual', 'sporty', 'dressy']]],
            ['footwear', 'mary_janes', 'Babet (Mary Jane)', 'leather Mary Jane shoes', ['regions' => $all, 'genders' => ['girl'], 'lengths' => ['mini', 'knee', 'midi'], 'style' => ['dressy', 'romantic']]],
            ['footwear', 'ballet_flats', 'Babet', 'ballet flats', ['regions' => $all, 'genders' => ['girl'], 'style' => ['romantic', 'dressy']]],
            ['footwear', 'strap_sandals', 'Bantlı sandalet', 'flat strap sandals', ['regions' => $all, 'genders' => $both, 'style' => ['casual', 'romantic'], 'season' => ['warm']]],
            ['footwear', 'espadrilles', 'Espadril', 'canvas espadrilles', ['regions' => $all, 'genders' => ['girl'], 'lengths' => ['knee', 'midi', 'maxi'], 'style' => ['casual', 'romantic'], 'season' => ['warm']]],
            ['footwear', 'loafers', 'Loafer', 'leather loafers', ['regions' => $all, 'genders' => $both, 'style' => ['dressy']]],
            ['footwear', 'ankle_boots', 'Kısa bot', 'low ankle boots', ['regions' => $all, 'genders' => $both, 'lengths' => $short, 'style' => ['casual'], 'season' => ['cool']]],
            // Çorap (uzun üründe görünmez → yalnız kısa boyda)
            ['socks', 'none', 'Çorap görünmesin', '', ['regions' => $all, 'genders' => $both]],
            ['socks', 'white_ankle_socks', 'Beyaz bilek çorabı', 'white ankle socks', ['regions' => $all, 'genders' => $both, 'lengths' => $short, 'style' => ['casual', 'sporty']]],
            ['socks', 'crew_socks', 'Beyaz kolej çorabı', 'white crew socks', ['regions' => $all, 'genders' => $both, 'lengths' => $short, 'style' => ['sporty']]],
            ['socks', 'frill_socks', 'Fırfırlı çorap', 'white frilly ankle socks', ['regions' => $all, 'genders' => ['girl'], 'lengths' => $short, 'style' => ['romantic', 'dressy']]],
            // Aksesuar (en fazla bir tane; üst/takım/tek parça ürünlerde göğüste aksesuar yok)
            ['accessory', 'none', 'Aksesuar yok', '', ['regions' => $all, 'genders' => $both]],
            ['accessory', 'headband', 'Saç bandı', 'a plain fabric headband', ['regions' => $all, 'genders' => ['girl'], 'placement' => 'hair']],
            ['accessory', 'hair_clip', 'Toka', 'a small plain hair clip', ['regions' => $all, 'genders' => ['girl'], 'placement' => 'hair']],
            ['accessory', 'scrunchie', 'Saç lastiği (scrunchie)', 'a fabric scrunchie on the ponytail', ['regions' => $all, 'genders' => ['girl'], 'placement' => 'hair']],
            ['accessory', 'simple_watch', 'Sade kol saati', "a simple kids' wristwatch", ['regions' => $all, 'genders' => $both, 'placement' => 'wrist']],
            ['accessory', 'crossbody_bag', 'Küçük çapraz çanta', 'a small crossbody bag', ['regions' => $notTop, 'genders' => ['girl'], 'placement' => 'chest']],
            // Renk (renklenebilir tamamlayıcı parçaya uygulanır)
            ['color', 'white', 'Beyaz', 'white', ['style' => ['casual', 'sporty', 'dressy', 'romantic']]],
            ['color', 'cream', 'Krem', 'cream', ['style' => ['casual', 'romantic', 'dressy']]],
            ['color', 'butter_yellow', 'Açık sarı', 'pale butter-yellow', ['style' => ['casual', 'romantic']]],
            ['color', 'soft_pink', 'Pudra pembe', 'soft pink', ['style' => ['romantic', 'casual']]],
            ['color', 'sky_blue', 'Açık mavi', 'light sky-blue', ['style' => ['casual', 'sporty']]],
            ['color', 'sage_green', 'Adaçayı yeşili', 'sage green', ['style' => ['casual', 'romantic']]],
            ['color', 'lavender', 'Lavanta', 'lavender', ['style' => ['romantic', 'casual']]],
            ['color', 'navy', 'Lacivert', 'navy', ['style' => ['dressy', 'sporty', 'casual']]],
            ['color', 'heather_grey', 'Gri melanj', 'heather-grey', ['style' => ['sporty', 'casual']]],
            ['color', 'beige', 'Bej', 'beige', ['style' => ['dressy', 'casual']]],
            ['color', 'olive', 'Haki', 'olive', ['style' => ['casual']]],
            ['color', 'black', 'Siyah', 'black', ['style' => ['dressy', 'casual']]],
        ];

        $now = now();
        $order = [];
        DB::table('creative_stylist_options')->insert(array_map(function ($r) use ($now, &$order) {
            $order[$r[0]] = ($order[$r[0]] ?? 0) + 1;

            return ['kind' => $r[0], 'key' => $r[1], 'label_tr' => $r[2], 'prompt_en' => $r[3], 'tags' => json_encode($r[4]),
                'is_active' => true, 'sort_order' => $order[$r[0]], 'created_at' => $now, 'updated_at' => $now];
        }, $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_stylist_options');
    }
};
