<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renk başına mokap giydirme (2026-10-06): aynı baz görsel (beden grubu + kombin + poz) ürünün her rengi
 * için o rengin yalın fotoğrafıyla ayrı giydirilir. color = product_variants.color_name; '' = renksiz /
 * tek renk (NULL değil — Postgres tekillikte NULL'ları ayrı sayar). Tekillik rengi de içerir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('creative_tryon_results', 'color')) {
            Schema::table('creative_tryon_results', function (Blueprint $table) {
                $table->string('color', 60)->default('')->after('shot');
            });
        }

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline']);
            $table->unique(['product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline', 'color']);
        });
    }

    public function down(): void
    {
        // Eski tekilliği bozacak (yalnız rengi farklı) satırlar: en eskisi kalır.
        $dupes = DB::table('creative_tryon_results')
            ->select('product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline', DB::raw('min(id) as keep_id'))
            ->groupBy('product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline')->havingRaw('count(*) > 1')->get();
        foreach ($dupes as $d) {
            DB::table('creative_tryon_results')->where(['product_id' => $d->product_id, 'mannequin_id' => $d->mannequin_id,
                'pose_id' => $d->pose_id, 'shot' => $d->shot, 'pipeline' => $d->pipeline])->where('id', '!=', $d->keep_id)->delete();
        }

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline', 'color']);
            $table->unique(['product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline']);
            $table->dropColumn('color');
        });
    }
};
