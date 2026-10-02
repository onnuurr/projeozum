<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * product_categories.icon serbest emoji yerine Lucide ikon adı saklar
 * (seçilebilir set: resources/js/categoryIcons.js). Bilinen emojiler karşılığına,
 * tanınmayan / boş değerler varsayılan 'package'a çevrilir.
 */
return new class extends Migration
{
    private const MAP = [
        '📦' => 'package',
        '👕' => 'shirt',
        '👔' => 'shirt',
        '👖' => 'person-standing',
        '👗' => 'venus',
        '🧥' => 'umbrella',
        '👟' => 'footprints',
    ];

    public function up(): void
    {
        foreach (self::MAP as $emoji => $name) {
            DB::table('product_categories')->where('icon', $emoji)->update(['icon' => $name]);
        }

        // Lucide adı biçiminde olmayan (eşlenmemiş emoji, boş) kayıtlar varsayılana düşer
        DB::table('product_categories')
            ->where(fn ($q) => $q->whereNull('icon')->orWhere('icon', ''))
            ->update(['icon' => 'package']);
        DB::table('product_categories')
            ->whereNotIn('icon', array_unique(array_values(self::MAP)))
            ->get(['id', 'icon'])
            ->reject(fn ($row) => preg_match('/^[a-z0-9-]+$/', $row->icon))
            ->each(fn ($row) => DB::table('product_categories')->where('id', $row->id)->update(['icon' => 'package']));
    }

    public function down(): void
    {
        // Ad → ilk eşleşen emoji (👕/👔 ikisi de 'shirt' idi; geri dönüşte 👕 olur).
        // Seçiciden gelen ve emoji karşılığı olmayan adlar '📦'ya döner.
        $reverse = [];
        foreach (self::MAP as $emoji => $name) {
            $reverse[$name] ??= $emoji;
        }

        foreach ($reverse as $name => $emoji) {
            DB::table('product_categories')->where('icon', $name)->update(['icon' => $emoji]);
        }

        DB::table('product_categories')
            ->whereNotIn('icon', array_keys(self::MAP))
            ->update(['icon' => '📦']);
    }
};
