<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reçeteye tahmini işçilik/fason birim maliyeti. Yalnız ön maliyet (geçici kartın
 * satış fiyatı önerisi) içindir — gerçek maliyet iş emri tamamlanınca adımlardan hesaplanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_boms', function (Blueprint $table) {
            $table->decimal('extra_cost_per_unit', 12, 2)->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_boms', function (Blueprint $table) {
            $table->dropColumn('extra_cost_per_unit');
        });
    }
};
