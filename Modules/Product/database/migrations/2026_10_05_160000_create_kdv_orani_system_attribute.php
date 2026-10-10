<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KDV oranı, Ürün Ayarları → Özellikler'de yönetilen `kdv_orani` sistem özelliği olur (2026-10-05).
 * Seçenek adları orandır (%0 / %1 / %10 / %20); üründe seçilirse kategori oranını ezer, boşsa kategori
 * zinciri geçerli (VatRateService). Sistem özelliği: kategori ataması gerekmez, silinemez, Bagisto'ya
 * attribute olarak gitmez (ProductAttribute::SYSTEM_CODES).
 *
 * Model olayı tetiklenmesin diye DB üzerinden yazılır (Bagisto'ya push edilecek bir şey yok).
 *
 * down(): ürünlerdeki `kdv_orani` değerlerini kaldırır, özelliği ve seçeneklerini siler. Ürünler
 * kategori oranına döner.
 */
return new class extends Migration
{
    private const CODE = 'kdv_orani';

    private const OPTIONS = ['%0', '%1', '%10', '%20'];

    public function up(): void
    {
        DB::transaction(function () {
            if (DB::table('product_attributes')->where('code', self::CODE)->exists()) {
                return;
            }

            $now = now();
            $attributeId = DB::table('product_attributes')->insertGetId([
                'code' => self::CODE,
                'name' => 'KDV Oranı',
                'type' => 'select',
                'is_filterable' => false,
                'sort_order' => (int) DB::table('product_attributes')->max('sort_order') + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::OPTIONS as $i => $name) {
                DB::table('product_attribute_options')->insert([
                    'product_attribute_id' => $attributeId,
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('products')->whereNotNull('attributes')->orderBy('id')->select(['id', 'attributes'])
                ->each(function ($product) {
                    $values = json_decode($product->attributes, true) ?: [];
                    if (! array_key_exists(self::CODE, $values)) {
                        return;
                    }
                    unset($values[self::CODE]);
                    DB::table('products')->where('id', $product->id)->update(['attributes' => $values ? json_encode($values) : null]);
                });

            // Seçenekler FK cascade ile gider.
            DB::table('product_attributes')->where('code', self::CODE)->delete();
        });
    }
};
