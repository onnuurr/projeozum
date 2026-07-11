<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siparişe kargo firması + bayinin anlaşmalı kargo müşteri kodu.
 *
 * Yeni siparişlerde ikisi de checkout'ta ZORUNLU (StoreDropshipOrderRequest);
 * DB'de nullable, çünkü bu migration'dan önceki geçmiş siparişlerde alan yok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'carrier_id')) {
                $table->foreignId('carrier_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('carriers')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'cargo_customer_code')) {
                $table->string('cargo_customer_code', 64)->nullable()->after('carrier_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'carrier_id')) {
                $table->dropConstrainedForeignId('carrier_id');
            }
            if (Schema::hasColumn('orders', 'cargo_customer_code')) {
                $table->dropColumn('cargo_customer_code');
            }
        });
    }
};
