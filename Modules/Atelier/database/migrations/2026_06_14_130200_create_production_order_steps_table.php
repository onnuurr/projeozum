<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operations')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->enum('location_type', ['in_house', 'fason'])->default('in_house');
            $table->foreignId('fason_supplier_id')->nullable()->constrained('fason_suppliers')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->unsignedInteger('input_qty')->default(0);
            $table->unsignedInteger('output_qty')->default(0);
            $table->unsignedInteger('scrap_qty')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('step_cost', 14, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'sequence']);
            $table->index('status');
            $table->index('fason_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_steps');
    }
};
