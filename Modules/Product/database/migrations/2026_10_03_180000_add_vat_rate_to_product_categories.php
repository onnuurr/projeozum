<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori KDV oranı (%). SaaS fiyatları KDV hariç tutulur; mağazaya (Bagisto) KDV dahil gider ve
 * bayi siparişinde KDV ayrı hesaplanır (VatRateService). Boş = üst kategoriden miras; zincirde hiç
 * oran yoksa config('product.default_vat_rate').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });
    }
};
