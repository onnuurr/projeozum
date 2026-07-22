<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product lifecycle (Faz 2 - Product Intelligence Platform). Sadece yapısal:
 * katalog/erişilebilirlik sorguları bu kolona göre filtrelenmiyor (kasıtlı,
 * bkz. plan Faz 2 madde 2). Varsayılan 'published' — bugün oluşturulan her
 * ürün zaten anında canlı; bu davranışı korur. Postgres'te default, mevcut
 * satırları da doldurur, ayrı backfill gerekmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status')->default('published')->after('slug');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
