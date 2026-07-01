<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_outgoing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 64);
            $table->string('invoice_type', 32)->default('standalone'); // sales_order|tenant_sale|standalone
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('tenant_invoice_id')->nullable()->constrained('tenant_invoices')->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_tax_number', 32)->nullable();
            $table->string('buyer_address')->nullable();
            $table->date('issue_date');
            $table->string('currency', 3)->default('TRY');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 32)->default('draft');

            // e-Fatura/GİB alanları — entegratör henüz seçilmedi, hepsi nullable kalır.
            // Bkz. Modules\Finance\Contracts\EInvoiceProviderInterface + NullEInvoiceProvider.
            $table->string('efatura_uuid')->nullable();
            $table->string('efatura_provider', 64)->nullable();
            $table->string('efatura_status', 32)->default('not_sent');
            $table->json('efatura_raw_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('pdf_path')->nullable();

            $table->foreignId('converted_from_proforma_id')
                ->nullable()
                ->constrained('finance_proforma_invoices')
                ->nullOnDelete();

            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'issue_date']);
        });

        // finance_proforma_invoices.converted_invoice_id, Faz 3'te bu tablo henüz
        // yokken yumuşak referans (FK'sız) olarak eklenmişti; şimdi gerçek FK kurulur.
        Schema::table('finance_proforma_invoices', function (Blueprint $table) {
            $table->foreign('converted_invoice_id')
                ->references('id')->on('finance_outgoing_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_proforma_invoices', function (Blueprint $table) {
            $table->dropForeign(['converted_invoice_id']);
        });

        Schema::dropIfExists('finance_outgoing_invoices');
    }
};
