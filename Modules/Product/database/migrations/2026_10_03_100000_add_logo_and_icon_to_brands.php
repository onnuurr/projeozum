<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marka görseli (Markalar sayfası listede: önce logo, yoksa ikon, o da yoksa baş harfler).
 * logo_path: Media diski üzerinde relative path (brands/x.png); URL okuma anında Media::url().
 * icon: ortak ikon kaydındaki ad (product_categories.icon ile aynı biçim, 64 karakter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('icon', 64)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'icon']);
        });
    }
};
