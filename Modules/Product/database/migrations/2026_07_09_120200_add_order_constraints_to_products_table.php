<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B sipariş kısıtları (satır bazında): minimum sipariş adedi ve koli katı.
 * Her ikisi de nullable = kısıt yok. Checkout satır adedini bu kurallara karşı doğrular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'min_order_qty')) {
                $table->unsignedInteger('min_order_qty')->nullable()->after('free_shipping');
            }
            if (! Schema::hasColumn('products', 'order_multiple')) {
                $table->unsignedInteger('order_multiple')->nullable()->after('min_order_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['order_multiple', 'min_order_qty'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
