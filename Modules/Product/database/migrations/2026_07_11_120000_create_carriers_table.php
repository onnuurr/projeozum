<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kargo firmaları — superadmin yönetimli referans tablosu.
 *
 * Dropship checkout'unda bayi her siparişte bir kargo firması seçer ve kendi
 * anlaşmalı kargo müşteri kodunu girer (orders.carrier_id + cargo_customer_code).
 * Düşük hacimli iş varlığı → Tenant gibi bilerek korunur (Prunable YOK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            // Takip linki şablonu; {code} yer tutucusu gönderi takip no ile değiştirilir.
            $table->string('tracking_url_template', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carriers');
    }
};
