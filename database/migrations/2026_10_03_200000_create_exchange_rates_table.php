<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Döviz kurları (TCMB gösterge kurları, günlük bülten). Paylaşılan altyapı: hammadde alımı gibi
 * dövizli işlemler belirli bir tarihteki kuru buradan okur (ExchangeRateService). Geçmiş tarihli
 * çevrim için gerektiğinden bilerek Prunable değil; büyüme ~20 satır/iş günü.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->date('rate_date');
            $table->unsignedInteger('unit')->default(1);
            $table->decimal('forex_buying', 14, 6)->nullable();
            $table->decimal('forex_selling', 14, 6)->nullable();
            $table->decimal('banknote_buying', 14, 6)->nullable();
            $table->decimal('banknote_selling', 14, 6)->nullable();
            $table->string('source', 20)->default('tcmb');
            $table->timestamps();

            $table->unique(['currency_code', 'rate_date']);
            $table->index('rate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
