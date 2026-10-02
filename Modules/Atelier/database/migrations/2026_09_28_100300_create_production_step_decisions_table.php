<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aşama bazında "iç mi / hangi fasona, adet başı kaç ₺" karar izi. Aşamanın güncel
 * değerleri production_order_steps'te; her değişiklik buraya yeni satır olarak düşer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_step_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_step_id')->constrained('production_order_steps')->cascadeOnDelete();
            $table->string('location_type', 16);
            $table->foreignId('fason_supplier_id')->nullable()->constrained('fason_suppliers')->nullOnDelete();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['production_order_step_id', 'created_at']);
        });

        // Mevcut aşamaların güncel kararı ilk satır olarak taşınır.
        DB::table('production_order_steps')->orderBy('id')->each(function ($step) {
            DB::table('production_step_decisions')->insert([
                'production_order_step_id' => $step->id,
                'location_type'            => $step->location_type,
                'fason_supplier_id'        => $step->fason_supplier_id,
                'unit_cost'                => $step->unit_cost,
                'created_at'               => $step->created_at ?? now(),
                'updated_at'               => $step->created_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_step_decisions');
    }
};
