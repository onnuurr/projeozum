<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\StoreBulkStockRequest;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;

/**
 * Toplu stok işlemi sayfası: bir veya daha çok ürün seçilir, bütün varyantları tek tabloda
 * gelir, tek kayıtla depoya giriş / çıkış / sayım yapılır (StockService::bulk()). İşlemin logu
 * StockOperation + bağlı StockMovement satırlarıdır (Stok › Hareket Geçmişi).
 */
class StockBulkController extends Controller
{
    private const SEARCH_LIMIT = 20;

    public function __construct(private StockService $stock) {}

    /** Stok listesinden seçilen satırlar ?variant_ids[]= ile gelirse o ürünler hazır yüklenir. */
    public function create(Request $request): Response
    {
        $variantIds = array_map('intval', (array) $request->input('variant_ids', []));
        $productIds = $variantIds ? ProductVariant::query()->whereIn('id', $variantIds)->distinct()->pluck('product_id')->all() : [];
        $warehouseId = $request->integer('warehouse_id') ?: $this->stock->defaultWarehouseId();

        return Inertia::render('Product::StockBulk', [
            'warehouses'  => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'code']),
            'warehouseId' => $warehouseId,
            'preset'      => $productIds && $warehouseId ? $this->productRows(Product::query()->whereIn('id', $productIds), $warehouseId) : [],
            'presetVariantIds' => $variantIds,
        ]);
    }

    /**
     * Ürün arama (ad / ürün SKU / varyant SKU) ya da product_ids ile yeniden yükleme (depo değişince
     * miktarlar tazelenir). Her ürün bütün varyantlarıyla ve seçili depodaki miktarlarla döner.
     */
    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id'  => ['required', 'integer', 'exists:warehouses,id'],
            'q'             => ['nullable', 'string', 'max:100'],
            'product_ids'   => ['nullable', 'array', 'max:200'],
            'product_ids.*' => ['integer'],
        ]);

        $query = Product::query();
        if (! empty($data['product_ids'])) {
            $query->whereIn('id', $data['product_ids']);
        } else {
            $q = trim((string) ($data['q'] ?? ''));
            if ($q === '') {
                return response()->json(['data' => []]);
            }
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(fn ($w) => $w->whereLike('name', $like)->orWhereLike('sku', $like)
                ->orWhereHas('variants', fn ($v) => $v->whereLike('sku', $like)))
                ->limit(self::SEARCH_LIMIT);
        }

        return response()->json(['data' => $this->productRows($query, (int) $data['warehouse_id'])]);
    }

    public function store(StoreBulkStockRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $operation = $this->stock->bulk((int) $data['warehouse_id'], $data['mode'], $data['lines'], $data['note'] ?? null, $request->user()?->id);

        return redirect()->route('products.stocks.history', ['operation_id' => $operation->id])
            ->with('success', __('product::messages.stock_bulk_saved', ['count' => $operation->line_count]));
    }

    /** @return list<array<string,mixed>> */
    private function productRows($query, int $warehouseId): array
    {
        $products = $query->whereHas('variants')->with(['variants' => fn ($v) => $v->orderBy('sort_order')])
            ->orderBy('name')->get(['id', 'name', 'sku']);
        $stocks = Stock::query()->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $products->flatMap(fn ($p) => $p->variants->pluck('id')))
            ->get(['product_variant_id', 'quantity', 'reserved_quantity'])->keyBy('product_variant_id');

        return $products->map(fn (Product $p) => [
            'id'       => $p->id,
            'name'     => $p->name,
            'sku'      => $p->sku,
            'variants' => $p->variants->map(fn (ProductVariant $v) => [
                'id'         => $v->id,
                'sku'        => $v->sku,
                'size'       => $v->size,
                'color_name' => $v->color_name,
                'color_hex'  => $v->color_hex,
                'quantity'   => (int) ($stocks[$v->id]->quantity ?? 0),
                'reserved'   => (int) ($stocks[$v->id]->reserved_quantity ?? 0),
            ])->values()->all(),
        ])->values()->all();
    }
}
