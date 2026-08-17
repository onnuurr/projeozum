<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagisto'dan gelen `order.created`/`order.cancelled` webhook'larını Bagisto'nun
 * kendi sipariş id'siyle eşlemek için (idempotency + iptal lookup'ı).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('bagisto_order_id', 32)->nullable()->unique()->after('order_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['bagisto_order_id']);
            $table->dropColumn('bagisto_order_id');
        });
    }
};
