<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori durumu artık otomatik: (alt kategoriler dahil) yayında ürünü yoksa pasif, varsa aktif
 * (CategoryStatusService). manually_passive = kullanıcının bilerek pasife aldığı kategori; otomatik
 * aktifleştirme bunu ezmez. Mevcut pasif kategoriler bilinçli karar sayılıp işaretlenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('manually_passive')->default(false)->after('status');
        });

        DB::table('product_categories')->where('status', 'passive')->update(['manually_passive' => true]);
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('manually_passive');
        });
    }
};
