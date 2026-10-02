<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aşama parti kaydı: bir aşamadan çıkan sağlam + fire adet. production_order_steps'teki
 * input/output/scrap toplamları bu satırlardan türetilen denormalize değerlerdir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_step_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_step_id')->constrained('production_order_steps')->cascadeOnDelete();
            $table->unsignedInteger('good_qty')->default(0);
            $table->unsignedInteger('scrap_qty')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['production_order_step_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_step_batches');
    }
};
