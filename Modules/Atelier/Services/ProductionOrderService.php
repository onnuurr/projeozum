<?php

namespace Modules\Atelier\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderStep;
use Modules\Atelier\Models\ProductionStepBatch;
use Modules\Atelier\Models\ProductionStepDecision;
use Modules\Product\Services\ProductService;

/**
 * İş emri yaşam döngüsü:
 *   draft ─plan()→ planned ─recordBatch()→ in_progress ─close()→ completed
 *   (draft|planned|in_progress, stoğa giriş yokken) ─cancel()→ cancelled
 *
 * Hammadde planlamada rezerve edilir, tüketen aşamada (kesim) parti kaydedildikçe düşer.
 * Aşamalar sıralıdır ama parti parti ilerler: k. aşama en fazla (k−1). aşamanın sağlam
 * çıkışı kadar adet işleyebilir. Son aşamanın sağlam çıkışı receive() ile varyant bazında
 * kısmi olarak stoğa alınır.
 */
class ProductionOrderService
{
    public function __construct(
        private BomService $bom,
        private MaterialReservationService $reservations,
        private FinishedGoodsService $finishedGoods,
        private ProductService $products,
        private ProductRouteService $routes,
    ) {}

    /**
     * Adımlar ürün rotasının (ya da önerilen rotanın) sihirbazda düzenlenmiş kopyasıdır;
     * save_as_product_route verilirse bu rota ürünün rotası olarak da kaydedilir.
     *
     * @param  array<string, mixed>  $data  StoreProductionOrderRequest::validated()
     */
    public function create(array $data, ?User $actor): ProductionOrder
    {
        $itemsTotal = (int) array_sum(array_column($data['items'], 'planned_qty'));
        if ($itemsTotal !== (int) $data['planned_qty']) {
            throw new InvalidArgumentException(
                "Varyant adetleri toplamı ({$itemsTotal}) planlanan adede ({$data['planned_qty']}) eşit olmalı."
            );
        }

        foreach ($data['steps'] as $step) {
            $this->assertDecision($step['location_type'], $step['fason_supplier_id'] ?? null);
        }

        return DB::transaction(function () use ($data, $actor) {
            $order = ProductionOrder::create([
                'code'         => 'IE-' . strtoupper(Str::random(8)),
                'product_id'   => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status'       => ProductionOrder::STATUS_DRAFT,
                'planned_qty'  => $data['planned_qty'],
                'due_date'     => $data['due_date'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $actor?->id,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'planned_qty'        => $item['planned_qty'],
                ]);
            }

            foreach (collect($data['steps'])->sortBy('sequence')->values() as $i => $step) {
                $created = $order->steps()->create([
                    'operation_id'      => $step['operation_id'],
                    'sequence'          => $i + 1,
                    'location_type'     => $step['location_type'],
                    'fason_supplier_id' => $step['location_type'] === 'fason' ? $step['fason_supplier_id'] : null,
                    'unit_cost'         => $step['unit_cost'] ?? 0,
                    'status'            => ProductionOrderStep::STATUS_PENDING,
                ]);

                $this->logDecision($created, $actor);
            }

            if (! empty($data['save_as_product_route'])) {
                $this->routes->replace((int) $data['product_id'], $data['steps']);
            }

            return $order;
        });
    }

    /**
     * draft → planned: reçete ihtiyacını rezerve eder (stok düşmez), tahmini malzeme maliyetini yazar.
     */
    public function plan(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== ProductionOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Yalnızca taslak iş emri planlanabilir.');
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('product');
            $requirements = $this->bom->requirementsFor($order->product, (float) $order->planned_qty);

            $this->reservations->reserve($order, $requirements);

            $order->update([
                'status'        => ProductionOrder::STATUS_PLANNED,
                'material_cost' => round(array_sum(array_column($requirements, 'line_cost')), 2),
            ]);

            return $order;
        });
    }

    /**
     * Aşamanın iç/fason kararını değiştirir; yalnız aşamada henüz parti yokken. Her değişiklik iz bırakır.
     *
     * @param  array{location_type:string, fason_supplier_id?:int|null, unit_cost?:float|null, note?:string|null}  $data
     */
    public function changeDecision(ProductionOrderStep $step, array $data, ?User $actor): ProductionOrderStep
    {
        $order = $step->order;
        if (! $order->isOpen()) {
            throw new InvalidArgumentException('Tamamlanmış veya iptal edilmiş iş emri düzenlenemez.');
        }
        if ($step->batches()->exists()) {
            throw new InvalidArgumentException('Bu aşamada üretim kaydı var; karar artık değiştirilemez.');
        }
        $this->assertDecision($data['location_type'], $data['fason_supplier_id'] ?? null);

        return DB::transaction(function () use ($step, $data, $actor) {
            $step->update([
                'location_type'     => $data['location_type'],
                'fason_supplier_id' => $data['location_type'] === 'fason' ? $data['fason_supplier_id'] : null,
                'unit_cost'         => $data['unit_cost'] ?? $step->unit_cost,
            ]);

            $this->logDecision($step, $actor, $data['note'] ?? null);

            return $step;
        });
    }

    /**
     * Aşamadan bir parti çıkışı kaydeder (sağlam + fire). Sıra kuralı: işlenen adet, aşamaya
     * gelmiş ama henüz işlenmemiş adedi (alınabilir) aşamaz. Tüketen aşamada hammadde düşülür.
     */
    public function recordBatch(ProductionOrderStep $step, int $good, int $scrap, ?string $note, ?User $actor): ProductionStepBatch
    {
        if ($good < 0 || $scrap < 0 || $good + $scrap === 0) {
            throw new InvalidArgumentException('Sağlam ya da fire adedi girilmeli.');
        }

        return DB::transaction(function () use ($step, $good, $scrap, $note, $actor) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($step->production_order_id);

            if (! in_array($order->status, [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
                throw new InvalidArgumentException(
                    $order->status === ProductionOrder::STATUS_DRAFT
                        ? 'Önce iş emrini planlayın (hammadde rezervi).'
                        : 'Tamamlanmış veya iptal edilmiş iş emrine üretim girilemez.'
                );
            }

            $steps = $order->steps()->with('operation')->get();
            $step = $steps->firstWhere('id', $step->id);
            $available = $this->availableFor($order, $steps, $step);

            if ($good + $scrap > $available) {
                throw new InvalidArgumentException(
                    "Bu aşamaya gelmiş işlenmemiş adet {$available}; " . ($good + $scrap) . ' adet girilemez.'
                );
            }

            $batch = $step->batches()->create([
                'good_qty'   => $good,
                'scrap_qty'  => $scrap,
                'note'       => $note,
                'created_by' => $actor?->id,
            ]);

            if ($this->consumingStep($steps)?->id === $step->id) {
                $this->reservations->consume($order, $good + $scrap);
            }

            $output = (int) $step->output_qty + $good;
            $step->update([
                'output_qty' => $output,
                'scrap_qty'  => (int) $step->scrap_qty + $scrap,
                'input_qty'  => (int) $step->input_qty + $good + $scrap,
                'step_cost'  => round($output * (float) $step->unit_cost, 2),
                'started_at' => $step->started_at ?? now(),
            ]);

            $this->syncStepStatuses($order, $steps);

            if ($order->status === ProductionOrder::STATUS_PLANNED) {
                $order->update(['status' => ProductionOrder::STATUS_IN_PROGRESS]);
            }

            return $batch;
        });
    }

    /**
     * Son aşamanın sağlam çıkışından varyant bazında stoğa giriş (kısmi olabilir).
     * İlk girişte geçici kart (development) ürüne döner — stoğu olan ürün geçici kalmaz.
     *
     * @param  array<int, int>  $quantities  product_variant_id → adet
     */
    public function receive(ProductionOrder $order, array $quantities, ?User $actor): ProductionOrder
    {
        $quantities = array_filter(array_map('intval', $quantities), fn (int $q) => $q > 0);
        if ($quantities === []) {
            throw new InvalidArgumentException('Stoğa alınacak adet girilmeli.');
        }

        return DB::transaction(function () use ($order, $quantities, $actor) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
                throw new InvalidArgumentException('Bu durumdaki iş emrinden stoğa giriş yapılamaz.');
            }

            $receivable = $this->receivable($order);
            $total = array_sum($quantities);
            if ($total > $receivable) {
                throw new InvalidArgumentException("Stoğa alınabilecek adet {$receivable}; {$total} adet girilemez.");
            }

            $items = $order->items()->get()->keyBy('product_variant_id');
            foreach (array_keys($quantities) as $variantId) {
                if (! $items->has($variantId)) {
                    throw new InvalidArgumentException('Varyant bu iş emrine ait değil.');
                }
            }

            $order->loadMissing('product');
            if ((int) $order->received_qty === 0) {
                $this->products->promoteFromDevelopment(
                    $order->product,
                    (float) $order->product->purchase_price,
                    $actor,
                    "İlk üretim girişi: {$order->code}",
                );
            }

            $this->finishedGoods->receiveIntoStock($order, $quantities);

            foreach ($quantities as $variantId => $qty) {
                $item = $items->get($variantId);
                $item->update(['received_qty' => (int) $item->received_qty + $qty]);
            }

            $order->update(['received_qty' => (int) $order->received_qty + $total]);

            return $order;
        });
    }

    /**
     * planned|in_progress → completed: kalan rezerv serbest, gerçek maliyet ve birim maliyet yazılır,
     * ürün alış fiyatı gerçek birim maliyete çekilir. Stoğa alınmamış çıkış varken kapatılamaz.
     */
    public function close(ProductionOrder $order, ?User $actor): ProductionOrder
    {
        if (! in_array($order->status, [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('Bu durumdaki iş emri kapatılamaz.');
        }
        if ((int) $order->received_qty === 0) {
            throw new InvalidArgumentException('Stoğa alınmış ürün yok; iş emrini iptal edin.');
        }
        $receivable = $this->receivable($order);
        if ($receivable > 0) {
            throw new InvalidArgumentException("Son aşamada stoğa alınmamış {$receivable} adet var; önce stoğa alın.");
        }

        return DB::transaction(function () use ($order, $actor) {
            $order->loadMissing('steps', 'product');

            $this->reservations->release($order);

            $materialCost = $this->reservations->consumedCost($order);
            $fasonCost = (float) $order->steps->where('location_type', 'fason')->sum('step_cost');
            $laborCost = (float) $order->steps->where('location_type', 'in_house')->sum('step_cost');
            $totalCost = round($materialCost + $fasonCost + $laborCost, 2);
            $unitCost = round($totalCost / (int) $order->received_qty, 2);

            $order->update([
                'material_cost' => $materialCost,
                'fason_cost'    => $fasonCost,
                'labor_cost'    => $laborCost,
                'total_cost'    => $totalCost,
                'unit_cost'     => $unitCost,
                'status'        => ProductionOrder::STATUS_COMPLETED,
                'closed_at'     => now(),
                'closed_by'     => $actor?->id,
            ]);

            $this->products->updatePurchasePrice($order->product, $unitCost);

            return $order;
        });
    }

    /** Stoğa giriş yapılmamış emri iptal eder; kalan rezerv serbest, kesilmiş hammadde geri dönmez. */
    public function cancel(ProductionOrder $order, ?User $actor): ProductionOrder
    {
        if (! $order->isOpen()) {
            throw new InvalidArgumentException('Tamamlanmış veya iptal edilmiş iş emri iptal edilemez.');
        }
        if ((int) $order->received_qty > 0) {
            throw new InvalidArgumentException('Stoğa giriş yapılmış iş emri iptal edilemez; kapatın.');
        }

        return DB::transaction(function () use ($order, $actor) {
            $this->reservations->release($order);

            $order->update([
                'status'    => ProductionOrder::STATUS_CANCELLED,
                'closed_at' => now(),
                'closed_by' => $actor?->id,
            ]);

            return $order;
        });
    }

    /**
     * Aşamaya gelmiş ama henüz işlenmemiş adet. İlk aşamaya planlanan adet gelir,
     * sonrakilere bir önceki aşamanın sağlam çıkışı.
     *
     * @param  Collection<int, ProductionOrderStep>  $steps  sequence sıralı
     */
    public function availableFor(ProductionOrder $order, Collection $steps, ProductionOrderStep $step): int
    {
        $index = $steps->search(fn (ProductionOrderStep $s) => $s->id === $step->id);
        $incoming = $index === 0 ? (int) $order->planned_qty : (int) $steps[$index - 1]->output_qty;

        return max(0, $incoming - (int) $step->output_qty - (int) $step->scrap_qty);
    }

    /** Son aşamanın sağlam çıkışından henüz stoğa alınmamış adet. */
    public function receivable(ProductionOrder $order): int
    {
        $last = $order->steps()->reorder()->orderByDesc('sequence')->first();

        return max(0, (int) ($last?->output_qty ?? 0) - (int) $order->received_qty);
    }

    /**
     * Hammaddenin fiilen düştüğü aşama: rotadaki ilk "hammadde tüketir" operasyonu, yoksa ilk aşama.
     *
     * @param  Collection<int, ProductionOrderStep>  $steps
     */
    public function consumingStep(Collection $steps): ?ProductionOrderStep
    {
        return $steps->first(fn (ProductionOrderStep $s) => (bool) $s->operation?->consumes_materials)
            ?? $steps->first();
    }

    /**
     * Durumlar partilerden türetilir: hiç işlem yoksa bekliyor; önceki aşama bitmiş ve gelen
     * her şey işlenmişse bitti; aksi halde sürüyor.
     *
     * @param  Collection<int, ProductionOrderStep>  $steps
     */
    private function syncStepStatuses(ProductionOrder $order, Collection $steps): void
    {
        $previousDone = true;

        foreach ($steps as $s) {
            $s->refresh();
            $processed = (int) $s->output_qty + (int) $s->scrap_qty;

            $status = match (true) {
                $processed === 0 => ProductionOrderStep::STATUS_PENDING,
                $previousDone && $this->availableFor($order, $steps, $s) === 0 => ProductionOrderStep::STATUS_DONE,
                default => ProductionOrderStep::STATUS_IN_PROGRESS,
            };

            if ($status !== $s->status) {
                $s->update([
                    'status'       => $status,
                    'completed_at' => $status === ProductionOrderStep::STATUS_DONE ? now() : null,
                ]);
            }

            $previousDone = $status === ProductionOrderStep::STATUS_DONE;
        }
    }

    private function assertDecision(string $locationType, mixed $fasonSupplierId): void
    {
        if ($locationType === 'fason' && empty($fasonSupplierId)) {
            throw new InvalidArgumentException('Fason aşama için fasoncu seçilmeli.');
        }
    }

    private function logDecision(ProductionOrderStep $step, ?User $actor, ?string $note = null): void
    {
        ProductionStepDecision::create([
            'production_order_step_id' => $step->id,
            'location_type'            => $step->location_type,
            'fason_supplier_id'        => $step->fason_supplier_id,
            'unit_cost'                => $step->unit_cost,
            'note'                     => $note,
            'decided_by'               => $actor?->id,
        ]);
    }
}
