<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('product_status', 16)->default('active');
            $table->string('approval_status', 16)->default('not_sent');
            $table->string('store_name')->nullable();
            $table->string('model_code', 64)->nullable();
            $table->string('category_path')->nullable();
            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 8)->default('TL');
            $table->decimal('variant_extra_price', 12, 2)->default(0);
            $table->string('delivery_template')->nullable();
            $table->unsignedInteger('shipping_time')->nullable();
            $table->json('variants')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'marketplace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_marketplace_listings');
    }
};
