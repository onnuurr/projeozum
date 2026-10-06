<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beden grubu başına mokap (2026-10-06): aynı ürünün aynı pozu her yaş grubunun mankeniyle ayrı
 * üretilir (sitede beden seçilince o yaştaki mankenin görselleri). Tekillik mankeni de içerir:
 * (product_id, mannequin_id, pose_id, shot, pipeline). Yalnız gevşetir — mevcut satırlar bozulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'pose_id', 'shot', 'pipeline']);
            $table->unique(['product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline']);
        });
    }

    public function down(): void
    {
        // Eski tekilliği bozacak satırlar (aynı ürün+poz+kadraj+hat, farklı manken): en eskisi kalır.
        $dupes = DB::table('creative_tryon_results')
            ->select('product_id', 'pose_id', 'shot', 'pipeline', DB::raw('min(id) as keep_id'))
            ->groupBy('product_id', 'pose_id', 'shot', 'pipeline')->havingRaw('count(*) > 1')->get();
        foreach ($dupes as $d) {
            DB::table('creative_tryon_results')->where(['product_id' => $d->product_id, 'pose_id' => $d->pose_id, 'shot' => $d->shot, 'pipeline' => $d->pipeline])
                ->where('id', '!=', $d->keep_id)->delete();
        }

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'mannequin_id', 'pose_id', 'shot', 'pipeline']);
            $table->unique(['product_id', 'pose_id', 'shot', 'pipeline']);
        });
    }
};
