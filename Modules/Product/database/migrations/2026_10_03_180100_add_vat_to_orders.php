<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siparişte KDV ayrı: subtotal = KDV hariç (matrah), tax_amount = KDV, total = KDV dahil (bayinin
 * ödediği). Satırda uygulanan oran ve tutar saklanır — kategori oranı sonradan değişse de sipariş
 * ve iadeler o günkü oranla kalır. Mevcut siparişler 0 KDV ile kalır (önceden KDV ayrılmıyordu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('total_price');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'vat_amount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};
