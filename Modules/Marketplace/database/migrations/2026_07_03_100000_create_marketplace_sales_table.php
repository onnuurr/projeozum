<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('marketplace', 32);
            $table->string('external_order_id', 64);
            $table->string('external_line_id', 64);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('sold_price', 12, 2);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('net_revenue', 12, 2)->default(0);
            $table->string('status', 16)->default('new'); // new/shipped/delivered/cancelled/returned
            $table->jsonb('raw_payload')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'marketplace', 'external_order_id', 'external_line_id'], 'uq_mp_sale_external');
            $table->index(['tenant_id', 'marketplace', 'sold_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sales');
    }
};
