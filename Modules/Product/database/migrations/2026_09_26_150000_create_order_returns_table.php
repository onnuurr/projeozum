<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi iade talepleri (Bagisto RMA'nın SaaS karşılığı). Talep ve atölye ekspertizi
 * Bagisto'da açılır, karar / iade faturası kontrolü / para iadesi SaaS'ta yapılır
 * (bkz. Modules\Bagisto\Services\BagistoReturnService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_returns')) {
            return;
        }

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('bagisto_rma_id', 64)->unique();
            $table->string('bagisto_order_id', 64)->index();
            $table->string('status', 20)->default('requested')->index();

            // Bagisto'dan gelen talep anlık görüntüsü
            $table->string('bagisto_status', 64)->nullable();
            $table->string('reason')->nullable();
            $table->string('resolution', 64)->nullable();
            $table->text('information')->nullable();
            $table->string('cargo_method', 32)->nullable();
            $table->json('items')->nullable();
            $table->json('images')->nullable();
            $table->json('inspection')->nullable();
            $table->decimal('requested_amount', 12, 2)->default(0);

            // SaaS kararı
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            // İade faturası (bayi portaldan yükler, muhasebe kontrol eder)
            $table->boolean('invoice_required')->default(false);
            $table->string('invoice_status', 20)->nullable();
            $table->string('invoice_no', 64)->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->string('invoice_path')->nullable();
            $table->timestamp('invoice_uploaded_at')->nullable();
            $table->text('invoice_review_note')->nullable();
            $table->foreignId('invoice_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invoice_reviewed_at')->nullable();

            // Para iadesi
            $table->string('refund_method', 20)->nullable();
            $table->string('refund_reference')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamp('bagisto_created_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
