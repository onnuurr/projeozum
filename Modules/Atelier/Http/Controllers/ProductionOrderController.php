<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Atelier\Http\Requests\ReceiveProductionRequest;
use Modules\Atelier\Http\Requests\RecordStepBatchRequest;
use Modules\Atelier\Http\Requests\StoreProductionOrderRequest;
use Modules\Atelier\Http\Requests\UpdateStepDecisionRequest;
use Modules\Atelier\Models\FasonSupplier;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderMaterial;
use Modules\Atelier\Models\ProductionOrderStep;
use Modules\Atelier\Models\ProductionStepBatch;
use Modules\Atelier\Models\ProductionStepDecision;
use Modules\Atelier\Services\BomService;
use Modules\Atelier\Services\MaterialReservationService;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Atelier\Services\ProductRouteService;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;

class ProductionOrderController extends Controller
{
    public function __construct(
        private ProductionOrderService $service,
        private BomService $bom,
        private MaterialReservationService $reservations,
        private ProductRouteService $routes,
    ) {}

    public function index(Request $request): Response
    {
        $orders = ProductionOrder::query()
            ->with(['product:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProductionOrder $o) => [
                'id'          => $o->id,
                'code'        => $o->code,
                'productName' => $o->product?->name,
                'status'      => $o->status,
                'plannedQty'  => $o->planned_qty,
                'receivedQty' => $o->received_qty,
                'dueDate'     => optional($o->due_date)->format('Y-m-d'),
                'totalCost'   => (float) $o->total_cost,
            ]);

        return Inertia::render('Atelier::ProductionOrders', [
            'orders'     => $orders,
            'products'   => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'operations' => Operation::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'default_location', 'default_unit_cost', 'consumes_materials']),
            'fasonSuppliers' => FasonSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Henüz (iptal dışı) iş emri verilmemiş geçici kartlar — liste boşken kaybolmasınlar.
            'pendingCards' => Product::query()
                ->where('status', Product::STATUS_DEVELOPMENT)
                ->whereNotIn('id', ProductionOrder::query()
                    ->where('status', '!=', ProductionOrder::STATUS_CANCELLED)
                    ->select('product_id'))
                ->orderByDesc('id')
                ->get(['id', 'name', 'sku', 'created_at'])
                ->map(fn (Product $p) => [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'sku'       => $p->sku,
                    'createdAt' => optional($p->created_at)->format('Y-m-d'),
                ]),
            // "Üretilecek Ürün" kaydından gelindiyse sihirbaz bu ürünle açılır.
            'preselectProductId' => $request->integer('product_id') ?: null,
        ]);
    }

    /** Sihirbaz için: seçilen ürünün varyantları, rotası + BOM gereksinim önizlemesi (kullanılabilir hammaddeyle). */
    public function planPreview(Request $request): array
    {
        $product = Product::with('variants:id,product_id,size,color_name,sku')->findOrFail($request->integer('product_id'));
        $qty = max(0, $request->integer('planned_qty'));
        $requirements = $this->bom->requirementsFor($product, (float) $qty);

        $ids = array_column($requirements, 'material_id');
        $reserved = $this->reservations->reservedByMaterial($ids);
        $stock = Material::query()->whereIn('id', $ids)->pluck('current_stock', 'id');

        return [
            'route'        => $this->routes->forProduct($product->id),
            'variants'     => $product->variants->map(fn ($v) => [
                'id' => $v->id, 'size' => $v->size, 'colorName' => $v->color_name, 'sku' => $v->sku,
            ]),
            'requirements' => array_map(fn (array $r) => $r + [
                'reserved_qty'  => $reserved[$r['material_id']] ?? 0.0,
                'available_qty' => round((float) ($stock[$r['material_id']] ?? 0) - ($reserved[$r['material_id']] ?? 0), 3),
            ], $requirements),
        ];
    }

    public function show(ProductionOrder $productionOrder): Response
    {
        $productionOrder->load([
            'product:id,name,sku,status', 'warehouse:id,name,code',
            'items.variant:id,size,color_name,sku',
            'steps.operation:id,name,consumes_materials', 'steps.fasonSupplier:id,name',
            'steps.batches.creator:id,name', 'steps.decisions.fasonSupplier:id,name', 'steps.decisions.decider:id,name',
            'materials.material:id,name,unit',
        ]);

        $steps = $productionOrder->steps;
        $consuming = $this->service->consumingStep($steps);

        return Inertia::render('Atelier::ProductionOrderDetail', [
            'order' => [
                'id'            => $productionOrder->id,
                'code'          => $productionOrder->code,
                'productId'     => $productionOrder->product_id,
                'productName'   => $productionOrder->product?->name,
                'productStatus' => $productionOrder->product?->status,
                'warehouse'     => $productionOrder->warehouse?->name,
                'status'        => $productionOrder->status,
                'plannedQty'    => $productionOrder->planned_qty,
                'receivedQty'   => $productionOrder->received_qty,
                'receivable'    => $this->service->receivable($productionOrder),
                'dueDate'       => optional($productionOrder->due_date)->format('Y-m-d'),
                'closedAt'      => optional($productionOrder->closed_at)->format('Y-m-d H:i'),
                'materialCost'  => (float) $productionOrder->material_cost,
                'fasonCost'     => (float) $productionOrder->fason_cost,
                'laborCost'     => (float) $productionOrder->labor_cost,
                'totalCost'     => (float) $productionOrder->total_cost,
                'unitCost'      => (float) $productionOrder->unit_cost,
                'items'         => $productionOrder->items->map(fn ($i) => [
                    'id' => $i->id, 'variantId' => $i->product_variant_id, 'size' => $i->variant?->size,
                    'colorName' => $i->variant?->color_name, 'sku' => $i->variant?->sku,
                    'plannedQty' => $i->planned_qty, 'receivedQty' => $i->received_qty,
                ]),
                'steps'         => $steps->map(fn (ProductionOrderStep $st) => [
                    'id'               => $st->id,
                    'operationName'    => $st->operation?->name,
                    'sequence'         => $st->sequence,
                    'locationType'     => $st->location_type,
                    'fasonSupplierId'  => $st->fason_supplier_id,
                    'fasonSupplier'    => $st->fasonSupplier?->name,
                    'status'           => $st->status,
                    'available'        => $this->service->availableFor($productionOrder, $steps, $st),
                    'outputQty'        => $st->output_qty,
                    'scrapQty'         => $st->scrap_qty,
                    'unitCost'         => (float) $st->unit_cost,
                    'stepCost'         => (float) $st->step_cost,
                    'consumesMaterials' => $consuming?->id === $st->id,
                    'batches'          => $st->batches->take(20)->map(fn (ProductionStepBatch $b) => [
                        'id' => $b->id, 'goodQty' => $b->good_qty, 'scrapQty' => $b->scrap_qty, 'note' => $b->note,
                        'by' => $b->creator?->name, 'at' => optional($b->created_at)->format('Y-m-d H:i'),
                    ])->values(),
                    'decisions'        => $st->decisions->map(fn (ProductionStepDecision $d) => [
                        'id' => $d->id, 'locationType' => $d->location_type, 'fasonSupplier' => $d->fasonSupplier?->name,
                        'unitCost' => (float) $d->unit_cost, 'note' => $d->note,
                        'by' => $d->decider?->name, 'at' => optional($d->created_at)->format('Y-m-d H:i'),
                    ])->values(),
                ]),
                'materials'     => $productionOrder->materials->map(fn (ProductionOrderMaterial $m) => [
                    'id' => $m->id, 'name' => $m->material?->name, 'unit' => $m->material?->unit,
                    'requiredQty' => (float) $m->required_qty, 'consumedQty' => (float) $m->consumed_qty,
                    'openQty' => $m->openQty(), 'released' => $m->released_at !== null,
                ]),
            ],
            'fasonSuppliers' => FasonSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductionOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Ürün rotası reçete ana verisidir: kaydetmek reçete yetkisi ister, yoksa bayrak yok sayılır.
        $data['save_as_product_route'] = ($data['save_as_product_route'] ?? false)
            && $request->user()->can('atelier.bom.manage');

        try {
            $order = $this->service->create($data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('atelier.production-orders.show', $order)->with('success', 'İş emri oluşturuldu.');
    }

    public function plan(ProductionOrder $productionOrder): RedirectResponse
    {
        return $this->attempt('plan', fn () => $this->service->plan($productionOrder), 'İş emri planlandı, hammadde rezerve edildi.');
    }

    public function recordBatch(RecordStepBatchRequest $request, ProductionOrder $productionOrder, ProductionOrderStep $step): RedirectResponse
    {
        abort_unless($step->production_order_id === $productionOrder->id, 404);
        $data = $request->validated();

        return $this->attempt('batch', fn () => $this->service->recordBatch(
            $step, (int) $data['good_qty'], (int) ($data['scrap_qty'] ?? 0), $data['note'] ?? null, $request->user(),
        ), 'Üretim kaydı eklendi.');
    }

    public function updateDecision(UpdateStepDecisionRequest $request, ProductionOrder $productionOrder, ProductionOrderStep $step): RedirectResponse
    {
        abort_unless($step->production_order_id === $productionOrder->id, 404);

        return $this->attempt('decision', fn () => $this->service->changeDecision($step, $request->validated(), $request->user()), 'Aşama kararı güncellendi.');
    }

    public function receive(ReceiveProductionRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        return $this->attempt('receive', fn () => $this->service->receive($productionOrder, $request->quantities(), $request->user()), 'Ürünler stoğa alındı.');
    }

    public function close(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        return $this->attempt('close', fn () => $this->service->close($productionOrder, $request->user()), 'İş emri kapatıldı, maliyet hesaplandı.');
    }

    public function cancel(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        return $this->attempt('cancel', fn () => $this->service->cancel($productionOrder, $request->user()), 'İş emri iptal edildi, hammadde rezervi serbest bırakıldı.');
    }

    private function attempt(string $key, callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors([$key => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }
}
