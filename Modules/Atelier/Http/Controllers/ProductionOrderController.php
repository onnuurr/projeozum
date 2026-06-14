<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderStep;
use Modules\Atelier\Services\BomService;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;

class ProductionOrderController extends Controller
{
    public function __construct(
        private ProductionOrderService $service,
        private BomService $bom,
    ) {}

    public function index(): Response
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
                'producedQty' => $o->produced_qty,
                'dueDate'     => optional($o->due_date)->format('Y-m-d'),
                'totalCost'   => (float) $o->total_cost,
            ]);

        return Inertia::render('Atelier::ProductionOrders', [
            'orders'     => $orders,
            'products'   => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'operations' => Operation::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'default_location', 'default_unit_cost']),
        ]);
    }

    /** Sihirbaz için: seçilen ürünün varyantları + BOM gereksinim önizlemesi. */
    public function planPreview(Request $request): array
    {
        $product = Product::with('variants:id,product_id,size,color_name,sku')->findOrFail($request->integer('product_id'));
        $qty = max(0, $request->integer('planned_qty'));

        return [
            'variants'     => $product->variants->map(fn ($v) => [
                'id' => $v->id, 'size' => $v->size, 'colorName' => $v->color_name, 'sku' => $v->sku,
            ]),
            'requirements' => $this->bom->requirementsFor($product, (float) $qty),
        ];
    }

    public function show(ProductionOrder $productionOrder): Response
    {
        $productionOrder->load([
            'product:id,name,sku', 'warehouse:id,name,code',
            'items.variant:id,size,color_name,sku',
            'steps.operation:id,name', 'steps.fasonSupplier:id,name',
        ]);

        return Inertia::render('Atelier::ProductionOrderDetail', [
            'order' => [
                'id'           => $productionOrder->id,
                'code'         => $productionOrder->code,
                'productName'  => $productionOrder->product?->name,
                'warehouse'    => $productionOrder->warehouse?->name,
                'status'       => $productionOrder->status,
                'plannedQty'   => $productionOrder->planned_qty,
                'producedQty'  => $productionOrder->produced_qty,
                'materialCost' => (float) $productionOrder->material_cost,
                'fasonCost'    => (float) $productionOrder->fason_cost,
                'laborCost'    => (float) $productionOrder->labor_cost,
                'totalCost'    => (float) $productionOrder->total_cost,
                'unitCost'     => (float) $productionOrder->unit_cost,
                'items'        => $productionOrder->items->map(fn ($i) => [
                    'id' => $i->id, 'size' => $i->variant?->size, 'colorName' => $i->variant?->color_name,
                    'plannedQty' => $i->planned_qty, 'producedQty' => $i->produced_qty, 'scrapQty' => $i->scrap_qty,
                ]),
                'steps'        => $productionOrder->steps->map(fn (ProductionOrderStep $st) => [
                    'id' => $st->id, 'operationName' => $st->operation?->name, 'sequence' => $st->sequence,
                    'locationType' => $st->location_type, 'fasonSupplier' => $st->fasonSupplier?->name,
                    'status' => $st->status, 'inputQty' => $st->input_qty, 'outputQty' => $st->output_qty,
                    'scrapQty' => $st->scrap_qty, 'unitCost' => (float) $st->unit_cost, 'stepCost' => (float) $st->step_cost,
                ]),
            ],
            'fasonSuppliers' => \Modules\Atelier\Models\FasonSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id'              => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouse_id'            => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'planned_qty'             => ['required', 'integer', 'min:1'],
            'due_date'                => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.planned_qty'     => ['required', 'integer', 'min:0'],
            'steps'                   => ['required', 'array', 'min:1'],
            'steps.*.operation_id'    => ['required', 'integer', Rule::exists('operations', 'id')],
            'steps.*.sequence'        => ['required', 'integer', 'min:1'],
            'steps.*.location_type'   => ['required', Rule::in(['in_house', 'fason'])],
            'steps.*.fason_supplier_id' => ['nullable', 'integer', Rule::exists('fason_suppliers', 'id')],
            'steps.*.unit_cost'       => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $order = ProductionOrder::create([
                'code'         => 'IE-' . strtoupper(Str::random(8)),
                'product_id'   => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status'       => ProductionOrder::STATUS_DRAFT,
                'planned_qty'  => $data['planned_qty'],
                'due_date'     => $data['due_date'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $request->user()?->id,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'planned_qty'        => $item['planned_qty'],
                ]);
            }

            foreach ($data['steps'] as $step) {
                $order->steps()->create([
                    'operation_id'      => $step['operation_id'],
                    'sequence'          => $step['sequence'],
                    'location_type'     => $step['location_type'],
                    'fason_supplier_id' => $step['fason_supplier_id'] ?? null,
                    'unit_cost'         => $step['unit_cost'] ?? 0,
                    'status'            => ProductionOrderStep::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->route('atelier.production-orders.index')->with('success', 'İş emri oluşturuldu.');
    }

    public function plan(ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->service->plan($productionOrder);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }

        return back()->with('success', 'İş emri planlandı, hammadde düşüldü.');
    }

    public function complete(ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->service->complete($productionOrder);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['complete' => $e->getMessage()]);
        }

        return back()->with('success', 'İş emri tamamlandı, ürünler stoğa alındı.');
    }

    /** Adım durumu/miktar/fason güncelle. */
    public function updateStep(Request $request, ProductionOrder $productionOrder, ProductionOrderStep $step): RedirectResponse
    {
        abort_unless($step->production_order_id === $productionOrder->id, 404);

        $data = $request->validate([
            'status'            => ['required', Rule::in(['pending', 'in_progress', 'done'])],
            'input_qty'         => ['nullable', 'integer', 'min:0'],
            'output_qty'        => ['nullable', 'integer', 'min:0'],
            'scrap_qty'         => ['nullable', 'integer', 'min:0'],
            'fason_supplier_id' => ['nullable', 'integer', Rule::exists('fason_suppliers', 'id')],
            'unit_cost'         => ['nullable', 'numeric', 'min:0'],
            'note'              => ['nullable', 'string', 'max:1000'],
        ]);

        $output = (int) ($data['output_qty'] ?? 0);
        $unitCost = (float) ($data['unit_cost'] ?? $step->unit_cost);

        $step->update([
            'status'            => $data['status'],
            'input_qty'         => $data['input_qty'] ?? $step->input_qty,
            'output_qty'        => $output,
            'scrap_qty'         => $data['scrap_qty'] ?? $step->scrap_qty,
            'fason_supplier_id' => $data['fason_supplier_id'] ?? $step->fason_supplier_id,
            'unit_cost'         => $unitCost,
            'step_cost'         => $output * $unitCost,
            'started_at'        => $data['status'] !== 'pending' ? ($step->started_at ?? now()) : null,
            'completed_at'      => $data['status'] === 'done' ? now() : null,
            'note'              => $data['note'] ?? $step->note,
        ]);

        if ($productionOrder->status === ProductionOrder::STATUS_PLANNED && $data['status'] !== 'pending') {
            $productionOrder->update(['status' => ProductionOrder::STATUS_IN_PROGRESS]);
        }

        return back()->with('success', 'Adım güncellendi.');
    }

    /** Varyant üretilen/fire miktarı güncelle. */
    public function updateItem(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $data = $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.id'            => ['required', 'integer'],
            'items.*.produced_qty'  => ['required', 'integer', 'min:0'],
            'items.*.scrap_qty'     => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $row) {
            $productionOrder->items()->where('id', $row['id'])->update([
                'produced_qty' => $row['produced_qty'],
                'scrap_qty'    => $row['scrap_qty'] ?? 0,
            ]);
        }

        return back()->with('success', 'Üretim miktarları güncellendi.');
    }

    public function cancel(ProductionOrder $productionOrder): RedirectResponse
    {
        $productionOrder->update(['status' => ProductionOrder::STATUS_CANCELLED]);

        return back()->with('success', 'İş emri iptal edildi.');
    }
}
