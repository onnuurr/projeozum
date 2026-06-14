<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjust']);
            $table->decimal('quantity', 14, 3);          // signed: out negatif
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('reason', 32);                // purchase/consume/scrap/correction
            $table->decimal('before_stock', 14, 3);
            $table->decimal('after_stock', 14, 3);
            $table->unsignedBigInteger('production_order_id')->nullable(); // tüketim izi (FK Faz 4'te eklenir)
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['material_id', 'created_at']);
            $table->index('reason');
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_movements');
    }
};
