<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * İş emri başına hammadde rezervi + tüketim izi. Açık rezerv = required − consumed
 * (released_at boşken). Kullanılabilir hammadde = materials.current_stock − Σ açık rezerv.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('per_unit_qty', 14, 4);                // fire dahil adet başı
            $table->decimal('required_qty', 14, 3);
            $table->decimal('consumed_qty', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);        // planlama anı snapshot
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['production_order_id', 'material_id']);
            $table->index(['material_id', 'released_at']);
        });

        // Eski plan() hammaddeyi planlamada düşüyordu: açık emirlerin tüketimi
        // "tamamen tüketilmiş" olarak taşınır ki yeni akış ikinci kez düşmesin.
        $open = DB::table('production_orders')
            ->whereIn('status', ['planned', 'in_progress'])
            ->get(['id', 'planned_qty']);

        foreach ($open as $order) {
            $consumed = DB::table('material_movements')
                ->where('production_order_id', $order->id)
                ->where('reason', 'consume')
                ->whereNull('deleted_at')
                ->groupBy('material_id')
                ->selectRaw('material_id, -SUM(quantity) as qty, MAX(unit_cost) as unit_cost')
                ->get();

            foreach ($consumed as $row) {
                $qty = (float) $row->qty;
                DB::table('production_order_materials')->insert([
                    'production_order_id' => $order->id,
                    'material_id'         => $row->material_id,
                    'per_unit_qty'        => $order->planned_qty > 0 ? round($qty / $order->planned_qty, 4) : 0,
                    'required_qty'        => $qty,
                    'consumed_qty'        => $qty,
                    'unit_cost'           => (float) $row->unit_cost,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_materials');
    }
};
