<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap Faz 6 — dış giyim bölgesi ("outerwear": ceket, mont, yelek, yağmurluk). Ürün başka bir kıyafetin
 * ÜSTÜNE giyilir: baz görselde manken iç kombiniyle (üst + alt) ve dış katmansız çekilir, giydirme dış
 * katmanı üste ekler. Veri:
 *  - Dış giyim kategorileri (eşleştirmesi yoksa) → outerwear.
 *  - Stilist seçenekleri: üst/alt ürün yanına uyan parçalar (ve ayakkabı/çorap/aksesuar) dış giyimde de kullanılır.
 *  - Mokapta kullanılan pozlar dış giyime de açılır.
 */
return new class extends Migration
{
    private const CATEGORIES = ['dış giyim', 'ceket', 'yağmurluk', 'yelek', 'mont', 'kaban'];

    public function up(): void
    {
        $now = now();
        if (Schema::hasTable('product_categories')) {
            $mapped = DB::table('creative_category_regions')->pluck('category_id')->all();
            foreach (DB::table('product_categories')->whereNull('deleted_at')->get(['id', 'name']) as $c) {
                if (in_array(mb_strtolower(trim((string) $c->name)), self::CATEGORIES, true) && ! in_array($c->id, $mapped, true)) {
                    DB::table('creative_category_regions')->insert(['category_id' => $c->id, 'region' => 'outerwear', 'length' => null,
                        'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        $this->eachRegions('creative_stylist_options', 'tags', function (array $tags) {
            $r = (array) ($tags['regions'] ?? []);
            if ($r !== [] && (in_array('top', $r, true) || in_array('bottom', $r, true)) && ! in_array('outerwear', $r, true)) {
                $tags['regions'] = [...$r, 'outerwear'];
            }

            return $tags;
        });
        $this->eachRegions('creative_poses', 'mockup_regions', fn (array $r) => $r !== [] && ! in_array('outerwear', $r, true) ? [...$r, 'outerwear'] : $r);
    }

    public function down(): void
    {
        DB::table('creative_category_regions')->where('region', 'outerwear')->delete();
        $strip = fn (array $r) => array_values(array_diff($r, ['outerwear']));
        $this->eachRegions('creative_stylist_options', 'tags', function (array $tags) use ($strip) {
            if (isset($tags['regions'])) {
                $tags['regions'] = $strip((array) $tags['regions']);
            }

            return $tags;
        });
        $this->eachRegions('creative_poses', 'mockup_regions', $strip);
    }

    /** JSON kolonunu satır satır dönüştürür (Postgres/sqlite ortak). */
    private function eachRegions(string $table, string $column, Closure $fn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        foreach (DB::table($table)->whereNotNull($column)->get(['id', $column]) as $row) {
            $value = json_decode((string) $row->{$column}, true);
            if (! is_array($value)) {
                continue;
            }
            $new = $fn($value);
            if ($new !== $value) {
                DB::table($table)->where('id', $row->id)->update([$column => json_encode($new)]);
            }
        }
    }
};
