<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_product_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Override: marka/kategori kuralı ne derse desin bu ürün için özel davranış.
            // is_blocked=true → kural açık olsa bile bu ürün gizli.
            // is_blocked=false → kural kapalı olsa bile bu ürün açık (whitelist exception).
            $table->boolean('is_blocked')->default(false);
            // Pazarlık fiyatı; null ise price_list / variant.price fallback.
            $table->decimal('custom_price', 14, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id'], 'uq_tenant_product');
            $table->index('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_product_access');
    }
};
