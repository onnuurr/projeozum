<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap Faz 4 — ayarlar sayfasının verisi:
 *  - creative_category_regions: kategori → mokap bölgesi (+ varsayılan boy). Kaydı olmayan kategori
 *    (ve üst kategorisi de yoksa) mokap dışıdır, eski hattan geçer. Önceden config
 *    creative.mockup.category_regions (kategori ADI ile) — burada aynı adlı bütün kategorilere taşınır.
 *  - creative_settings: Creative'in anahtar-değer ayarları (mokap otomatik tetik, günlük limit,
 *    varsayılan mankenler). Creative, Superadmin'in Setting modeline bağımlı olamaz (module.json).
 */
return new class extends Migration
{
    /** Eski config eşleştirmesi (kategori adı küçük harf → bölge [+ boy]). */
    private const LEGACY = [
        'etek'              => ['bottom', null],
        'şort'              => ['bottom', 'mini'],
        'alt giyim'         => ['bottom', null],
        'kot pantolon'      => ['bottom', 'maxi'],
        'tayt'              => ['bottom', 'maxi'],
        'tişört'            => ['top', null],
        'atlet'             => ['top', null],
        'sweatshirt'        => ['top', null],
        'kazak & hırka'     => ['top', null],
        'üst giyim'         => ['top', null],
        'takım'             => ['set', null],
        'alt üst takım'     => ['set', null],
        'ev giyim & pijama' => ['set', null],
        'elbise & tulum'    => ['one_piece', null],
        'tulum'             => ['one_piece', null],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('creative_category_regions')) {
            // Test (sqlite) veritabanında modül migration sırası gereği product_categories henüz olmayabilir.
            $hasCategories = Schema::hasTable('product_categories');
            Schema::create('creative_category_regions', function (Blueprint $table) use ($hasCategories) {
                $table->id();
                $fk = $table->foreignId('category_id')->unique();
                if ($hasCategories) {
                    $fk->constrained('product_categories')->cascadeOnDelete();
                }
                $table->string('region', 20);
                $table->string('length', 10)->nullable();
                $table->timestamps();
            });

            $now = now();
            foreach ($hasCategories ? DB::table('product_categories')->get(['id', 'name']) : [] as $c) {
                $map = self::LEGACY[mb_strtolower(trim((string) $c->name))] ?? null;
                if ($map) {
                    DB::table('creative_category_regions')->insert(['category_id' => $c->id, 'region' => $map[0], 'length' => $map[1],
                        'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        if (! Schema::hasTable('creative_settings')) {
            Schema::create('creative_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_settings');
        Schema::dropIfExists('creative_category_regions');
    }
};
