<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün domain event'lerinin kalıcı denetim izi (Faz 1 - Product Intelligence Platform).
 *
 * `Modules\Product\Listeners\RecordProductTimelineEntry` her `ProductDomainEvent`
 * için buraya bir satır yazar. `order_status_histories` ile aynı kategoride
 * ("kalıcı iş denetimi") olduğu için Prunable DEĞİLDİR. Silme kaskadı: ürün
 * silinirse geçmişi de gider (product_id FK cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_timeline_events');
    }
};
