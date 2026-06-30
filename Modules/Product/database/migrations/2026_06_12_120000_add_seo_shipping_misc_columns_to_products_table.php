<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // SEO (slug zaten mevcut)
            $table->string('meta_title', 191)->nullable()->after('slug');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('meta_keywords', 255)->nullable()->after('meta_description');

            // Kargo
            $table->decimal('weight', 8, 3)->nullable()->after('free_shipping');
            $table->decimal('desi', 8, 2)->nullable()->after('weight');
            $table->string('shipping_time', 50)->nullable()->after('desi');
            $table->decimal('shipping_fee', 10, 2)->nullable()->after('shipping_time');

            // Diğer
            $table->string('barcode', 64)->nullable()->after('origin_country');
            $table->boolean('is_domestic')->default(false)->after('barcode');
            $table->string('manufacturer_code', 64)->nullable()->after('is_domestic');
            $table->string('gtip_code', 32)->nullable()->after('manufacturer_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'meta_keywords',
                'weight',
                'desi',
                'shipping_time',
                'shipping_fee',
                'barcode',
                'is_domestic',
                'manufacturer_code',
                'gtip_code',
            ]);
        });
    }
};
