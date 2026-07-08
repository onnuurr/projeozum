<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giden fatura kalemleri. e-Fatura/e-Arşiv entegratörü (Trendyol e-Faturam) kalem
 * bazlı KDV kırılımı istediği için OutgoingInvoice'a kalem tablosu eklenir.
 * Tutarlar burada TL (decimal) tutulur; entegratöre gönderirken kuruşa çevrilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_outgoing_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_invoice_id')
                ->constrained('finance_outgoing_invoices')
                ->cascadeOnDelete();
            $table->string('item_name');
            // UBL birim kodu (C62 = adet). Entegratör invoiceLines[].unitCode alanı.
            $table->string('unit_code', 10)->default('C62');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);   // KDV hariç birim fiyat (TL)
            $table->decimal('vat_rate', 5, 2)->default(20);      // KDV oranı %
            $table->decimal('taxable_amount', 12, 2)->default(0); // satır net (qty*unit_price)
            $table->decimal('vat_amount', 12, 2)->default(0);    // satır KDV
            $table->decimal('line_total', 12, 2)->default(0);    // satır brüt (net+KDV)
            $table->timestamps();

            $table->index('outgoing_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_outgoing_invoice_items');
    }
};
