<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maksimum satış adedi: bir siparişte bu üründen (tüm varyantlar toplamı) alınabilecek en fazla
 * adet. Boş = sınırsız. Mağazada (Bagisto) sepete ekleme/güncellemede SaasSync paketi uygular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('max_order_qty')->nullable()->after('order_multiple');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('max_order_qty');
        });
    }
};
