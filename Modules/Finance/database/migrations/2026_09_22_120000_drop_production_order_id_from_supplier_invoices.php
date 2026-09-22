<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alınan faturalar (SupplierInvoice) artık Atelier üretim emrine doğrudan
 * bağlanmıyor — tedarikçi faturası genelde iş bittikten sonra geliyor,
 * üretim zaten faturadan bağımsız gerçekleşmiş oluyor; fatura bir üretim
 * emrini "tetikleyen/atayan" mekanizma olmamalı (bkz.
 * docs/superpowers/specs/2026-09-22-modul-calisma-mantigi-revizyonu-design.md §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('finance_supplier_invoices', function (Blueprint $table) {
            $table->foreignId('production_order_id')
                ->nullable()
                ->after('category')
                ->constrained('production_orders')
                ->nullOnDelete();
        });
    }
};
