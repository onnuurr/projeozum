<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cinsiyet, ürün formundaki sabit alandan (products.gender: Erkek/Kadın/Unisex) Ürün Ayarları →
 * Özellikler'de yönetilen seçimli `cinsiyet` özelliğine taşınır (2026-10-04). Önceden ikisi yan
 * yanaydı (Erkek Çocuk'ta cinsiyet iki kez soruluyordu, mağazaya yalnızca özellik gidiyordu).
 *
 *  - `cinsiyet` (select) özelliği varsa kullanılır, yoksa açılır. Seçenekler Kız / Erkek / Unisex:
 *    "Kadın / Kız" (ya da "Kadın") seçeneği "Kız" olarak yeniden adlandırılır (id korunur).
 *  - Kök kategorilere zorunlu olarak atanır (alt kategoriler miras alır).
 *  - products.gender → products.attributes['cinsiyet'] (Kadın→Kız); üründe özellik değeri zaten
 *    varsa o korunur (mağazaya giden değer oydu). Sonra kolon kaldırılır.
 *
 * Model olayı tetiklenmesin diye DB üzerinden yazılır; Bagisto'ya deploy sırasında
 * `bagisto:sync-attribute-options` + `bagisto:sync-products` ile gider.
 *
 * down(): kolonu geri açar ve değeri özellikten geri yazar (Kız→Kadın, değer yoksa Unisex).
 * `cinsiyet` özelliği, seçenekleri ve kategori atamaları yerinde kalır: canlıda bu migration'dan
 * önce de ayarlardan açılmış bir kayıttı, silmek kullanıcı verisini yok eder.
 */
return new class extends Migration
{
    private const CODE = 'cinsiyet';

    /** Seçenek adı → eski products.gender değeri (sıra = seçenek sırası). */
    private const OPTIONS = ['Kız' => 'Kadın', 'Erkek' => 'Erkek', 'Unisex' => 'Unisex'];

    /** Mevcut seçenek adlarından "Kız"a çevrilecek olanlar (küçük harf). */
    private const GIRL_ALIASES = ['kadın / kız', 'kadın/kız', 'kız / kadın', 'kadın', 'kız'];

    public function up(): void
    {
        DB::transaction(function () {
            $now = now();
            $attribute = DB::table('product_attributes')->where('code', self::CODE)->first();

            if ($attribute && $attribute->type !== 'select') {
                throw new RuntimeException("'".self::CODE."' özelliği '{$attribute->type}' tipinde; cinsiyet taşıması için select olmalı, elle incelenmeli.");
            }

            $attributeId = $attribute?->id ?? DB::table('product_attributes')->insertGetId([
                'code' => self::CODE,
                'name' => 'Cinsiyet',
                'type' => 'select',
                'is_filterable' => true,
                'sort_order' => (int) DB::table('product_attributes')->max('sort_order') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Seçenekler: mevcutlar ada göre eşlenir (Kadın / Kız → Kız), eksikler eklenir
            $existing = DB::table('product_attribute_options')->where('product_attribute_id', $attributeId)->pluck('name', 'id');
            $optionIds = [];
            foreach ($existing as $id => $name) {
                $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
                $target = in_array($key, self::GIRL_ALIASES, true) ? 'Kız' : collect(array_keys(self::OPTIONS))->first(fn ($o) => mb_strtolower($o) === $key);
                if ($target !== null && ! isset($optionIds[$target])) {
                    $optionIds[$target] = $id;
                }
            }

            $sort = 1;
            foreach (array_keys(self::OPTIONS) as $name) {
                if (isset($optionIds[$name])) {
                    DB::table('product_attribute_options')->where('id', $optionIds[$name])->update(['name' => $name, 'sort_order' => $sort, 'updated_at' => $now]);
                } else {
                    $optionIds[$name] = DB::table('product_attribute_options')->insertGetId([
                        'product_attribute_id' => $attributeId,
                        'name' => $name,
                        'sort_order' => $sort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $sort++;
            }
            // Eşlenemeyen eski seçenekler (varsa) listenin sonuna
            DB::table('product_attribute_options')->where('product_attribute_id', $attributeId)
                ->whereNotIn('id', array_values($optionIds))->orderBy('sort_order')->pluck('id')
                ->each(function ($id) use (&$sort) {
                    DB::table('product_attribute_options')->where('id', $id)->update(['sort_order' => $sort++]);
                });

            // Kök kategorilere zorunlu atama (varsa zorunlu yapılır)
            foreach (DB::table('product_categories')->whereNull('parent_id')->pluck('id') as $categoryId) {
                $pivot = DB::table('category_product_attribute')->where(['category_id' => $categoryId, 'product_attribute_id' => $attributeId]);
                if ($pivot->exists()) {
                    $pivot->update(['required' => true, 'updated_at' => $now]);
                } else {
                    DB::table('category_product_attribute')->insert([
                        'category_id' => $categoryId,
                        'product_attribute_id' => $attributeId,
                        'required' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $byGender = collect(self::OPTIONS)->flip()->map(fn ($name) => $optionIds[$name])->all();

            DB::table('products')->orderBy('id')->select(['id', 'attributes', 'gender'])
                ->each(function ($product) use ($byGender) {
                    $attributes = json_decode($product->attributes ?? 'null', true) ?: [];
                    if (! empty($attributes[self::CODE])) {
                        return;
                    }
                    $optionId = $byGender[$product->gender] ?? null;
                    if ($optionId === null) {
                        return;
                    }
                    $attributes[self::CODE] = $optionId;
                    DB::table('products')->where('id', $product->id)->update(['attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE)]);
                });
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropColumn('gender');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('gender', ['Erkek', 'Kadın', 'Unisex'])->default('Unisex')->after('sku');
            $table->index('gender');
        });

        $attributeId = DB::table('product_attributes')->where('code', self::CODE)->value('id');
        if ($attributeId === null) {
            return;
        }

        $genderByOption = DB::table('product_attribute_options')->where('product_attribute_id', $attributeId)->pluck('name', 'id')
            ->map(fn ($name) => self::OPTIONS[$name] ?? null);

        DB::table('products')->whereNotNull('attributes')->orderBy('id')->select(['id', 'attributes'])
            ->each(function ($product) use ($genderByOption) {
                $attributes = json_decode($product->attributes ?? 'null', true) ?: [];
                $gender = $genderByOption[(int) ($attributes[self::CODE] ?? 0)] ?? null;
                if ($gender !== null) {
                    DB::table('products')->where('id', $product->id)->update(['gender' => $gender]);
                }
            });
    }
};
