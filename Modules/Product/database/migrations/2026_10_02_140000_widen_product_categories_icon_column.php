<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_categories.icon ortak ikon kaydındaki adı saklar; Lucide Lab ('lab:shirt-long-sleeve')
 * ve özel ikonlar ('oz:…') önekli olduğu için 16 karakter yetmiyor → 64 (superadmin_menus.icon ile aynı).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('icon', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        // 16 karaktere sığmayan adlar varsayılan ikona düşer, yoksa daraltma hata verir.
        DB::table('product_categories')
            ->whereRaw('LENGTH(icon) > 16')
            ->update(['icon' => 'package']);

        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('icon', 16)->nullable()->change();
        });
    }
};
