<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün durum geçişlerinin kalıcı denetim izi (Faz 2). `order_status_histories`
 * ile aynı desen. Kalıcı iş denetimi olduğu için Prunable DEĞİLDİR. Silme
 * kaskadı: ürün silinirse geçmişi de gider (product_id FK cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_status_histories');
    }
};
