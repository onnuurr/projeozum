<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mali iz — Prunable YOK. Her balance mutasyonu burada.
        Schema::create('tenant_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('type', 8); // 'debit' | 'credit'
            $table->decimal('amount', 14, 2);
            $table->string('reason', 64);
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('tenant_invoices')
                ->nullOnDelete();
            // İşlem sonrası anlık balance — tarihsel sorgu için (replay etmeye gerek kalmasın).
            $table->decimal('balance_after', 14, 2);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['order_id']);
            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_credit_ledger');
    }
};
