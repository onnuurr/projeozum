<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_images.url (tam URL) → product_images.path (relative).
 *
 * Görsel adresleri artık DB'de host gömülü tam URL olarak değil, yalnızca
 * relative path olarak tutulur; URL okuma anında App\Support\Media::url() ile
 * aktif medya diski (local /storage veya R2/CDN) üzerinden üretilir. Bu, CDN
 * geçişinde host'un satırlara gömülü kalması sorununu kökten çözer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_images', 'url') && ! Schema::hasColumn('product_images', 'path')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->renameColumn('url', 'path');
            });
        }

        // Mevcut tam URL'leri (http://host/storage/... veya /storage/...) relative
        // path'e indirge. '/storage/' işaretinden sonrasını al. PHP tarafında
        // yapılır → veritabanı-bağımsız (PostgreSQL prod + SQLite test). Tablo
        // küçük olduğundan tek geçiş yeterli; zaten relative satırlara dokunmaz.
        $marker = '/storage/';
        foreach (DB::table('product_images')->where('path', 'like', '%' . $marker . '%')->get(['id', 'path']) as $row) {
            $pos = strpos((string) $row->path, $marker);
            if ($pos !== false) {
                DB::table('product_images')
                    ->where('id', $row->id)
                    ->update(['path' => substr((string) $row->path, $pos + strlen($marker))]);
            }
        }
    }

    public function down(): void
    {
        // Relative path'leri tam URL'e geri çevir (http/data ile başlamayanlar).
        // PHP tarafında → veritabanı-bağımsız.
        $base = rtrim((string) config('app.url', 'http://localhost'), '/') . '/storage/';
        foreach (DB::table('product_images')->get(['id', 'path']) as $row) {
            $path = (string) $row->path;
            if ($path === '' || str_starts_with($path, 'http') || str_starts_with($path, 'data:')) {
                continue;
            }
            DB::table('product_images')->where('id', $row->id)->update(['path' => $base . $path]);
        }

        if (Schema::hasColumn('product_images', 'path') && ! Schema::hasColumn('product_images', 'url')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->renameColumn('path', 'url');
            });
        }
    }
};
