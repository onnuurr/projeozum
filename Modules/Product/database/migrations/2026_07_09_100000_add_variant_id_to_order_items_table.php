<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * order_items'a nullable FK product_variant_id ekler.
 *
 * İptal/iade ve raporlamada hangi varyantın satıldığını bilmek gerekir. color/size
 * string'leri görüntü snapshot'ı olarak kalır (varyant sonradan silinse/değişse bile
 * sipariş satırı okunabilir). Varyant silinirse FK null'a düşer (nullOnDelete) —
 * sipariş satırı silinmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_variants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'product_variant_id')) {
                $table->dropConstrainedForeignId('product_variant_id');
            }
        });
    }
};
