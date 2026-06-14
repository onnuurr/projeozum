<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('planned_qty')->default(0);
            $table->unsignedInteger('produced_qty')->default(0);
            $table->unsignedInteger('scrap_qty')->default(0);
            $table->timestamps();

            $table->unique(['production_order_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
    }
};
