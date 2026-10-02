<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün rotası (routing): ürünün üretim aşamaları, sırası ve varsayılan iç/fason kararı.
 * Reçetenin yanındaki ana veridir; yeni iş emri bu rotanın KOPYASIYLA açılır
 * (production_order_steps), rota sonradan değişse de açık iş emirleri etkilenmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_route_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operations')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('location_type', 16)->default('in_house');
            $table->foreignId('fason_supplier_id')->nullable()->constrained('fason_suppliers')->nullOnDelete();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_route_steps');
    }
};
