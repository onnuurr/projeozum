<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->jsonb('shipping_info')->nullable()->after('user_id');
            $table->string('payment_method', 32)->nullable()->after('shipping_info');
            $table->text('note')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_info', 'payment_method', 'note']);
        });
    }
};
