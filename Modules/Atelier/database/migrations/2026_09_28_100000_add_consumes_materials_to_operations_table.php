<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hammaddenin fiilen stoktan düştüğü aşama (kesim). İş emri planlanınca hammadde yalnız
 * rezerve edilir; bu bayraklı aşamada parti kaydedildikçe rezervden gerçek tüketim yapılır.
 * Rotada bayraklı aşama yoksa tüketim ilk aşamada olur (bkz. ProductionOrderService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->boolean('consumes_materials')->default(false)->after('default_unit_cost');
        });

        DB::table('operations')->where('code', 'kesim')->update(['consumes_materials' => true]);
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('consumes_materials');
        });
    }
};
