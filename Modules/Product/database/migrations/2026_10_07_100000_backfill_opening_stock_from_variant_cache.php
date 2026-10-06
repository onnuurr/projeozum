<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ürün formunda girilen başlangıç stoğu eskiden yalnız product_variants.stock önbelleğine
 * yazılıyordu; stocks tablosunda satırı olmadığı için Stok sayfasında görünmüyor, sipariş
 * tahsisi de bu stoğu bulamıyordu. Depo satırı hiç olmayan ve önbelleği > 0 olan varyantlar
 * için varsayılan depoya açılış stoğu (stok + giriş hareketi) yazılır. Önbellek değişmez.
 *
 * Geri alma: bu migration'ın yazdığı hareketler (notu MARKER) ve onların depo satırları silinir.
 */
return new class extends Migration
{
    private const MARKER = 'Açılış stoğu (ürün formundan aktarıldı)';

    public function up(): void
    {
        $warehouseId = DB::table('warehouses')->where('is_active', true)->whereNull('deleted_at')
            ->orderByDesc('is_default')->orderBy('id')->value('id');
        if ($warehouseId === null) {
            return;
        }

        $now = now();
        $variants = DB::table('product_variants')->where('stock', '>', 0)
            ->whereNotExists(fn ($q) => $q->from('stocks')->whereColumn('stocks.product_variant_id', 'product_variants.id'))
            ->get(['id', 'stock']);

        DB::transaction(function () use ($variants, $warehouseId, $now) {
            foreach ($variants as $v) {
                DB::table('stocks')->insert([
                    'product_variant_id' => $v->id, 'warehouse_id' => $warehouseId,
                    'quantity' => (int) $v->stock, 'reserved_quantity' => 0, 'min_quantity' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('stock_movements')->insert([
                    'product_variant_id' => $v->id, 'warehouse_id' => $warehouseId, 'type' => 'in',
                    'quantity' => (int) $v->stock, 'before_quantity' => 0, 'after_quantity' => (int) $v->stock,
                    'note' => self::MARKER, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $moves = DB::table('stock_movements')->where('note', self::MARKER)->get(['product_variant_id', 'warehouse_id']);
            foreach ($moves as $m) {
                DB::table('stocks')->where('product_variant_id', $m->product_variant_id)
                    ->where('warehouse_id', $m->warehouse_id)->delete();
            }
            DB::table('stock_movements')->where('note', self::MARKER)->delete();
        });
    }
};
