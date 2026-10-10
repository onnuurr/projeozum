<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menşei, ürün formundaki sabit 2 harfli kod alanından (products.origin_country) Ürün Ayarları →
 * Özellikler'de yönetilen seçimli `mensei` özelliğine taşınır (2026-10-04): ülke listesi ayarlardan
 * yönetilir, mağazada (Bagisto) seçimli ve filtrelenebilir olur.
 *
 *  - `mensei` (select) özelliği açılır; seçenekleri kullanılan kodların Türkçe adlarıdır (en az Türkiye).
 *  - Kök kategorilere atanır (alt kategoriler miras alır), zorunlu değil.
 *  - Her ürünün kodu products.attributes['mensei'] = seçenek id'si olarak yazılır, kolon kaldırılır.
 *
 * Model olayı tetiklenmesin diye DB üzerinden yazılır (Bagisto push'u deploy sırasında
 * `bagisto:sync-attribute-options` + `bagisto:sync-products` ile yapılır; Bagisto tarafında
 * SaasSync `2026_10_04_120000_make_mensei_saas_attribute` migration'ı önce çalışmalı).
 *
 * down(): kolonu geri açar (default TR), değeri seçenek adından koda çevirerek yazar, özelliği siler.
 */
return new class extends Migration
{
    private const CODE = 'mensei';

    /** ISO 3166-1 alpha-2 → Türkçe ad (bilinmeyen kod olduğu gibi seçenek adı olur). */
    private const COUNTRIES = [
        'TR' => 'Türkiye', 'CN' => 'Çin', 'BD' => 'Bangladeş', 'IN' => 'Hindistan', 'PK' => 'Pakistan',
        'VN' => 'Vietnam', 'EG' => 'Mısır', 'IT' => 'İtalya', 'PT' => 'Portekiz', 'ES' => 'İspanya',
        'DE' => 'Almanya', 'UZ' => 'Özbekistan', 'MA' => 'Fas', 'KH' => 'Kamboçya', 'ID' => 'Endonezya',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            if (DB::table('product_attributes')->where('code', self::CODE)->exists()) {
                throw new RuntimeException("'".self::CODE."' özelliği zaten var; menşei taşıması elle incelenmeli.");
            }

            $now = now();
            $attributeId = DB::table('product_attributes')->insertGetId([
                'code' => self::CODE,
                'name' => 'Menşei',
                'type' => 'select',
                'is_filterable' => true,
                'sort_order' => (int) DB::table('product_attributes')->max('sort_order') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $codes = DB::table('products')->whereNotNull('origin_country')->distinct()->pluck('origin_country')
                ->map(fn ($c) => strtoupper(trim($c)))->filter()->prepend('TR')->unique()->values();

            $optionIds = [];
            foreach ($codes as $i => $code) {
                $optionIds[$code] = DB::table('product_attribute_options')->insertGetId([
                    'product_attribute_id' => $attributeId,
                    'name' => self::COUNTRIES[$code] ?? $code,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (DB::table('product_categories')->whereNull('parent_id')->pluck('id') as $categoryId) {
                DB::table('category_product_attribute')->insert([
                    'category_id' => $categoryId,
                    'product_attribute_id' => $attributeId,
                    'required' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('products')->whereNotNull('origin_country')->orderBy('id')->select(['id', 'attributes', 'origin_country'])
                ->each(function ($product) use ($optionIds) {
                    $optionId = $optionIds[strtoupper(trim($product->origin_country))] ?? null;
                    if ($optionId === null) {
                        return;
                    }
                    $attributes = json_decode($product->attributes ?? 'null', true) ?: [];
                    $attributes[self::CODE] = $optionId;
                    DB::table('products')->where('id', $product->id)->update(['attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)]);
                });
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('origin_country');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('origin_country', 2)->default('TR')->after('material');
        });

        DB::transaction(function () {
            $attributeId = DB::table('product_attributes')->where('code', self::CODE)->value('id');
            if ($attributeId === null) {
                return;
            }

            $byName = array_flip(self::COUNTRIES);
            $codes = DB::table('product_attribute_options')->where('product_attribute_id', $attributeId)->pluck('name', 'id')
                ->map(fn ($name) => $byName[$name] ?? (strlen($name) === 2 ? strtoupper($name) : null));

            DB::table('products')->whereNotNull('attributes')->orderBy('id')->select(['id', 'attributes'])
                ->each(function ($product) use ($codes) {
                    $attributes = json_decode($product->attributes ?? 'null', true) ?: [];
                    if (! array_key_exists(self::CODE, $attributes)) {
                        return;
                    }
                    $code = $codes[(int) $attributes[self::CODE]] ?? null;
                    unset($attributes[self::CODE]);
                    DB::table('products')->where('id', $product->id)->update(array_filter([
                        'attributes' => $attributes === [] ? null : json_encode($attributes, JSON_UNESCAPED_UNICODE),
                        'origin_country' => $code,
                    ], fn ($v, $k) => $k === 'attributes' || $v !== null, ARRAY_FILTER_USE_BOTH));
                });

            // Seçenekler ve kategori atamaları FK cascade ile gider.
            DB::table('product_attributes')->where('id', $attributeId)->delete();
        });
    }
};
