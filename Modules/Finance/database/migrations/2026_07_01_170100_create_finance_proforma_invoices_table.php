<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('proforma_no', 64);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('tenant_invoice_id')->nullable()->constrained('tenant_invoices')->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_tax_number', 32)->nullable();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->string('currency', 3)->default('TRY');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 32)->default('draft');
            // finance_outgoing_invoices henüz oluşturulmadığı için (Faz 4 migration'ı sonra
            // gelir) burada gerçek bir FK constraint kurulmuyor; yumuşak referans + indeks
            // yeterli. Uygulama tarafı ProformaInvoiceService::convertToOutgoingInvoice() ile yönetir.
            $table->unsignedBigInteger('converted_invoice_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'valid_until']);
            $table->index('converted_invoice_id');
        });

        Schema::create('finance_proforma_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')
                ->constrained('finance_proforma_invoices')
                ->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_proforma_invoice_items');
        Schema::dropIfExists('finance_proforma_invoices');
    }
};
