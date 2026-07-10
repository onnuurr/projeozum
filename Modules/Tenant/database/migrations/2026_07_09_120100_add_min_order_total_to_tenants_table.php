<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B sipariş kısıtı: bayi bazında minimum sipariş tutarı (nullable = kısıt yok).
 * Checkout (CheckoutService::place) ara toplamı bu eşiğe karşı doğrular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'min_order_total')) {
                $table->decimal('min_order_total', 12, 2)->nullable()->after('discount_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'min_order_total')) {
                $table->dropColumn('min_order_total');
            }
        });
    }
};
