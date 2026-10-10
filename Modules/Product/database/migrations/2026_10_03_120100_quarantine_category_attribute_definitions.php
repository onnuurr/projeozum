<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Kategoriye özel özellik tanımları (Faz 3, category_attribute_definitions) yerini global
 * product_attributes + category_product_attribute'a bıraktı. Tablo canlıda boştu (0 kayıt,
 * 2026-10-03) ve hiçbir ürün değeri yoktu. CLAUDE.md kademeli silme: önce karantina adı,
 * bir-iki sürüm sonra gerçek dropIfExists.
 */
return new class extends Migration
{
    private const QUARANTINE = 'category_attribute_definitions_deprecated_20261003';

    public function up(): void
    {
        if (Schema::hasTable('category_attribute_definitions')) {
            Schema::rename('category_attribute_definitions', self::QUARANTINE);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::QUARANTINE)) {
            Schema::rename(self::QUARANTINE, 'category_attribute_definitions');
        }
    }
};
