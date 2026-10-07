<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\StoreStockMovementRequest;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\StockOperation;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): Response
    {
        $query = Stock::query()
            ->with([
                'variant:id,product_id,size,color_name,color_hex,sku',
                'variant.product:id,name,slug,sku,category_id,brand_id',
                'variant.product.category:id,name',
                'variant.product.brand:id,name',
                'warehouse:id,name,code',
            ]);

        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->whereHas('variant.product', fn ($q) => $q->where('category_id', $categoryId));
        }

        if ($request->boolean('critical_only')) {
            $query->belowMin();
        }

        $stocks = $query
            ->orderBy('warehouse_id')
            ->orderBy('product_variant_id')
            ->get()
            ->map(fn (Stock $s) => [
                'id'                 => $s->id,
                'variant_id'         => $s->product_variant_id,
                'warehouse_id'       => $s->warehouse_id,
                'warehouseName'      => $s->warehouse?->name,
                'warehouseCode'      => $s->warehouse?->code,
                'productId'          => $s->variant?->product?->id,
                'productName'        => $s->variant?->product?->name,
                'productSlug'        => $s->variant?->product?->slug,
                'category'           => $s->variant?->product?->category?->name,
                'brand'              => $s->variant?->product?->brand?->name,
                'size'               => $s->variant?->size,
                'colorName'          => $s->variant?->color_name,
                'colorHex'           => $s->variant?->color_hex,
                'sku'                => $s->variant?->sku,
                'quantity'           => $s->quantity,
                'reserved_quantity'  => $s->reserved_quantity,
                'min_quantity'       => $s->min_quantity,
                'available_quantity' => $s->available_quantity,
                'isCritical'         => $s->quantity <= $s->min_quantity,
            ]);

        return Inertia::render('Product::Stocks', [
            'stocks'      => $stocks,
            'warehouses'  => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'filters'     => [
                'warehouse_id'  => $request->integer('warehouse_id'),
                'category_id'   => $request->integer('category_id'),
                'critical_only' => $request->boolean('critical_only'),
            ],
        ]);
    }

    public function movement(StoreStockMovementRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->stock->move(
            variantId: (int) $data['product_variant_id'],
            warehouseId: (int) $data['warehouse_id'],
            type: $data['type'],
            qty: (int) $data['quantity'],
            reference: null,
            note: $data['note'] ?? null,
            userId: $request->user()?->id,
        );

        // Flash basılmıyor — çağıran sayfa (liste veya ürün kartı) onSuccess'te
        // kendi toast'unu gösteriyor. back(): hem Stocks.vue hem ProductCard.vue
        // aynı endpoint'i kullandığı için hedef route'u sabitlemek yerine
        // formu gönderen sayfaya döner.
        return back();
    }

    public function history(Request $request): Response
    {
        $query = StockMovement::query()
            ->with([
                'variant:id,product_id,size,color_name,sku',
                'variant.product:id,name,slug,sku',
                'warehouse:id,name,code',
                'user:id,name',
            ]);

        if ($variantId = $request->integer('variant_id')) {
            $query->where('product_variant_id', $variantId);
        }
        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }
        // Toplu işlemin satırları (StockService::bulk() hareketleri işleme reference ile bağlar).
        $operation = ($operationId = $request->integer('operation_id'))
            ? StockOperation::with(['warehouse:id,name,code', 'user:id,name'])->find($operationId) : null;
        if ($operationId) {
            $query->where('reference_type', (new StockOperation)->getMorphClass())->where('reference_id', $operationId);
        }

        $movements = $query
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (StockMovement $m) => [
                'id'              => $m->id,
                'type'             => $m->type,
                'quantity'         => $m->quantity,
                'before_quantity'  => $m->before_quantity,
                'after_quantity'   => $m->after_quantity,
                'note'             => $m->note,
                'productName'      => $m->variant?->product?->name,
                'productSlug'      => $m->variant?->product?->slug,
                'sku'              => $m->variant?->sku,
                'size'             => $m->variant?->size,
                'colorName'        => $m->variant?->color_name,
                'warehouseName'    => $m->warehouse?->name,
                'warehouseCode'    => $m->warehouse?->code,
                'userName'         => $m->user?->name,
                'createdAt'        => optional($m->created_at)->format('Y-m-d H:i'),
                'operationId'      => $m->reference_type === (new StockOperation)->getMorphClass() ? $m->reference_id : null,
            ]);

        return Inertia::render('Product::StockHistory', [
            'movements'  => $movements,
            'operation'  => $operation ? $this->mapOperation($operation) : null,
            // Son toplu işlemler (log başlıkları) — satırlarına operation_id filtresiyle inilir.
            'operations' => StockOperation::with(['warehouse:id,name,code', 'user:id,name'])
                ->latest('id')->limit(30)->get()->map(fn (StockOperation $o) => $this->mapOperation($o)),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters'    => [
                'variant_id'   => $request->integer('variant_id'),
                'warehouse_id' => $request->integer('warehouse_id'),
                'type'         => $request->string('type')->toString(),
                'operation_id' => $operationId ?: null,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function mapOperation(StockOperation $o): array
    {
        return [
            'id'            => $o->id,
            'mode'          => $o->mode,
            'note'          => $o->note,
            'warehouseName' => $o->warehouse?->name,
            'userName'      => $o->user?->name,
            'lineCount'     => $o->line_count,
            'skippedCount'  => $o->skipped_count,
            'totalIn'       => $o->total_in,
            'totalOut'      => $o->total_out,
            'createdAt'     => optional($o->created_at)->format('Y-m-d H:i'),
        ];
    }
}
