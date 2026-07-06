<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Değerler: 'b2c' (default), 'dropship' (tenant kullanıcısı tarafından açılan)
            $table->string('order_type', 16)->default('b2c')->after('status');

            $table->index(['order_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_type', 'status']);
            $table->dropColumn('order_type');
        });
    }
};
