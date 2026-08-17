<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Product\Events\StockChanged;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Models\Order;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;

/**
 * Tüm stok mutasyonlarının TEK noktası (D1).
 *
 * `move()` eski `StockController::movement()` gövdesinin birebir eşdeğeridir:
 * lockForUpdate → stok satırı mutasyonu → StockMovement denetim satırı →
 * product_variants.stock cache resync. Sipariş kesiminde `decrementForOrder()`
 * (D2 greedy depo tahsisi) ve iptalde `restockForOrder()` bu metodu kullanır.
 */
class StockService
{
    /**
     * Tek atomik stok hareketi. `$type` işaret semantiğini belirler:
     *   IN → +abs, OUT → -abs, ADJUSTMENT → ham değer.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException stok negatife düşerse (422).
     */
    public function move(
        int $variantId,
        int $warehouseId,
        string $type,
        int $qty,
        ?Model $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): StockMovement {
        $movement = DB::transaction(function () use ($variantId, $warehouseId, $type, $qty, $reference, $note, $userId) {
            $stock = Stock::query()
                ->where('product_variant_id', $variantId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = Stock::create([
                    'product_variant_id' => $variantId,
                    'warehouse_id'       => $warehouseId,
                    'quantity'           => 0,
                ]);
            }

            $before = $stock->quantity;
            $signed = match ($type) {
                StockMovement::TYPE_IN         => abs($qty),
                StockMovement::TYPE_OUT        => -abs($qty),
                StockMovement::TYPE_ADJUSTMENT => (int) $qty,
                default                        => (int) $qty,
            };

            $after = $before + $signed;
            if ($after < 0) {
                abort(422, 'Stok negatife düşemez.');
            }

            $stock->update(['quantity' => $after]);

            $movement = StockMovement::create([
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'type'               => $type,
                'quantity'           => $signed,
                'before_quantity'    => $before,
                'after_quantity'     => $after,
                'reference_type'     => $reference ? $reference->getMorphClass() : null,
                'reference_id'       => $reference?->getKey(),
                'note'               => $note,
                'user_id'            => $userId,
            ]);

            // product_variants.stock denormalize toplam (karar #4)
            ProductVariant::query()
                ->whereKey($variantId)
                ->update([
                    'stock' => (int) Stock::where('product_variant_id', $variantId)->sum('quantity'),
                ]);

            return $movement;
        });

        // `move()` genellikle bir dış transaction'ın (decrementForOrder gibi)
        // içinden çağrılır; bu durumda kendi DB::transaction()'ı sadece bir
        // savepoint'tir ve dış transaction hâlâ açık olabilir. afterCommit,
        // dispatch'i en dıştaki transaction gerçekten commit olana kadar
        // erteler — aksi halde kuyruğa giren listener (Bagisto push) henüz
        // commit edilmemiş/bayat stok değerini okuyabilir.
        DB::afterCommit(fn () => StockChanged::dispatch(ProductVariant::findOrFail($variantId)));

        return $movement;
    }

    /**
     * Bir varyant için tüm depolardaki satılabilir miktar = Σ(quantity - reserved_quantity).
     */
    public function availableForVariant(int $variantId): int
    {
        return (int) Stock::query()
            ->where('product_variant_id', $variantId)
            ->get(['quantity', 'reserved_quantity'])
            ->sum(fn (Stock $s) => max(0, $s->quantity - $s->reserved_quantity));
    }

    /**
     * Sipariş satırlarını depolara greedy tahsis edip TYPE_OUT hareketleri yazar (D2).
     *
     * Sıralama: önce varsayılan depo, sonra kullanılabilir miktara göre azalan.
     * Gerekirse bir satır birden çok depoya bölünür. Yetersizse hiçbir mutasyon
     * kalıcı olmadan `InsufficientStockException` fırlatır (dış transaction geri sarar).
     *
     * @param  EloquentCollection<int,\Illuminate\Database\Eloquent\Model>  $lines
     *         Her satırda `product_variant_id` (veya `variant_id`) ve `qty` bulunur.
     */
    public function decrementForOrder(Order $order, EloquentCollection $lines): void
    {
        DB::transaction(function () use ($order, $lines) {
            foreach ($lines as $line) {
                $variantId = (int) ($line->product_variant_id ?? $line->variant_id ?? 0);
                $needed    = (int) $line->qty;

                if ($variantId === 0 || $needed <= 0) {
                    // Varyantsız ürünler stok takibi yapılmaz; atla.
                    continue;
                }

                $this->allocateVariant($order, $variantId, $needed);
            }
        });
    }

    private function allocateVariant(Order $order, int $variantId, int $needed): void
    {
        $stocks = Stock::query()
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->get();

        $defaults = Warehouse::query()
            ->whereIn('id', $stocks->pluck('warehouse_id')->all())
            ->pluck('is_default', 'id');

        $available = static fn (Stock $s): int => max(0, $s->quantity - $s->reserved_quantity);

        $totalAvailable = (int) $stocks->sum($available);
        if ($totalAvailable < $needed) {
            throw new InsufficientStockException($variantId, $needed, $totalAvailable);
        }

        // Önce is_default depo, sonra kullanılabilir miktara göre azalan.
        $sorted = $stocks->sort(function (Stock $a, Stock $b) use ($defaults, $available) {
            $da = (bool) ($defaults[$a->warehouse_id] ?? false);
            $db = (bool) ($defaults[$b->warehouse_id] ?? false);
            if ($da !== $db) {
                return $da ? -1 : 1;
            }

            return $available($b) <=> $available($a);
        })->values();

        $remaining = $needed;
        foreach ($sorted as $stock) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $available($stock));
            if ($take <= 0) {
                continue;
            }

            $this->move(
                variantId: $variantId,
                warehouseId: $stock->warehouse_id,
                type: StockMovement::TYPE_OUT,
                qty: $take,
                reference: $order,
                note: 'Sipariş kesimi',
            );

            $remaining -= $take;
        }
    }

    /**
     * Bir siparişin OUT hareketlerini aynalayan IN hareketleri yazar (iptal iadesi).
     * Idempotent: bu sipariş için IN zaten yazılmışsa hiçbir şey yapmaz.
     */
    public function restockForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $alreadyRestocked = StockMovement::query()
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->getKey())
                ->where('type', StockMovement::TYPE_IN)
                ->exists();

            if ($alreadyRestocked) {
                return;
            }

            $outs = StockMovement::query()
                ->where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->getKey())
                ->where('type', StockMovement::TYPE_OUT)
                ->get();

            foreach ($outs as $out) {
                $this->move(
                    variantId: $out->product_variant_id,
                    warehouseId: $out->warehouse_id,
                    type: StockMovement::TYPE_IN,
                    qty: abs($out->quantity),
                    reference: $order,
                    note: 'Sipariş iptali - stok iadesi',
                );
            }
        });
    }
}
