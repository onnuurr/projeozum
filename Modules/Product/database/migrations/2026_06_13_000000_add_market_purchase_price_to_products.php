<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Piyasa (P:) ve alış (A:) fiyatları — ürün düzeyinde, opsiyonel.
            $table->decimal('market_price', 12, 2)->nullable()->after('old_price');
            $table->decimal('purchase_price', 12, 2)->nullable()->after('market_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['market_price', 'purchase_price']);
        });
    }
};
