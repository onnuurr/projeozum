<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.status kolonuna tekil index ekler (durum filtreli admin sipariş listesi).
 *
 * DİKKAT: Bileşik ['tenant_id','status'] index'i ZATEN
 * 2026_07_01_100000_add_tenant_id_to_orders_table migration'ında oluşturuluyor;
 * bileşik index'in lider kolonu tenant_id olduğu için yalnız status'a göre
 * filtreleyen sorgulara hizmet edemez. Bu yüzden burada YALNIZCA tekil status
 * index'i eklenir (duplicate değildir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
        });
    }
};
