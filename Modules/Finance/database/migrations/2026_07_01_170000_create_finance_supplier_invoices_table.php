<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 64);
            $table->string('supplier_name');
            $table->string('supplier_tax_number', 32)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 32)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->string('category', 100)->nullable();
            $table->foreignId('production_order_id')
                ->nullable()
                ->constrained('production_orders')
                ->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_supplier_invoices');
    }
};
