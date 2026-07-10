<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B checkout hizalaması (D4): sipariş düzeyi iskonto + cari vade tarihi.
 *
 * discount_rate/discount_amount cari iskonto pratiğidir — satır fiyat provenance'ı
 * (order_items.unit_price) temiz kalır; iskonto ara toplama uygulanır. due_date,
 * tenant.payment_term_days > 0 ise sipariş anında hesaplanır (vadeli satış).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'discount_rate')) {
                $table->decimal('discount_rate', 5, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_rate');
            }
            if (! Schema::hasColumn('orders', 'due_date')) {
                $table->date('due_date')->nullable()->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['due_date', 'discount_amount', 'discount_rate'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
