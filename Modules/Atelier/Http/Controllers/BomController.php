<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Atelier\Http\Requests\UpdateProductRouteRequest;
use Modules\Atelier\Models\FasonSupplier;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductRouteStep;
use Modules\Atelier\Services\BomService;
use Modules\Atelier\Services\ProductRouteService;
use Modules\Product\Models\Product;

class BomController extends Controller
{
    public function index(): Response
    {
        $boms = ProductBom::query()
            ->with(['product:id,name,sku', 'lines.material:id,name,unit'])
            ->where('is_active', true)
            ->get()
            ->map(fn (ProductBom $b) => [
                'id'          => $b->id,
                'productId'   => $b->product_id,
                'productName' => $b->product?->name,
                'name'        => $b->name,
                'extraCostPerUnit' => (float) $b->extra_cost_per_unit,
                'lines'       => $b->lines->map(fn ($l) => [
                    'materialId'      => $l->material_id,
                    'materialName'    => $l->material?->name,
                    'unit'            => $l->material?->unit,
                    'quantityPerUnit' => (float) $l->quantity_per_unit,
                    'wastePct'        => (float) $l->waste_pct,
                ]),
            ]);

        // Ürün id → rota adımları (reçete kartında özet + düzenleme)
        $routes = ProductRouteStep::query()
            ->with(['operation:id,name', 'fasonSupplier:id,name'])
            ->whereIn('product_id', $boms->pluck('productId'))
            ->orderBy('sequence')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($steps) => $steps->map(fn (ProductRouteStep $s) => [
                'operation_id'      => $s->operation_id,
                'operationName'     => $s->operation?->name,
                'sequence'          => $s->sequence,
                'location_type'     => $s->location_type,
                'fason_supplier_id' => $s->fason_supplier_id,
                'fasonSupplierName' => $s->fasonSupplier?->name,
                'unit_cost'         => (float) $s->unit_cost,
            ])->values());

        return Inertia::render('Atelier::Boms', [
            'boms'           => $boms,
            'routes'         => $routes,
            'operations'     => Operation::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'default_location', 'default_unit_cost']),
            'fasonSuppliers' => FasonSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products'       => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'materials'      => Material::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    /** Ürün rotasını (üretim aşamaları + varsayılan iç/fason kararı) yazar. */
    public function updateRoute(UpdateProductRouteRequest $request, Product $product, ProductRouteService $routes): RedirectResponse
    {
        try {
            $routes->replace($product->id, $request->validated()['steps']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['steps' => $e->getMessage()]);
        }

        return back()->with('success', 'Üretim rotası kaydedildi.');
    }

    public function store(Request $request, BomService $boms): RedirectResponse
    {
        $data = $this->validateBom($request);

        $boms->replaceActive($data['product_id'], $data['name'], $data['lines'], (float) ($data['extra_cost_per_unit'] ?? 0));

        return redirect()->route('atelier.boms.index')->with('success', 'Reçete kaydedildi.');
    }

    public function destroy(ProductBom $bom): RedirectResponse
    {
        $bom->delete();

        return redirect()->route('atelier.boms.index')->with('success', 'Reçete silindi.');
    }

    private function validateBom(Request $request): array
    {
        return $request->validate([
            'product_id'                 => ['required', 'integer', Rule::exists('products', 'id')],
            'name'                       => ['required', 'string', 'max:191'],
            'extra_cost_per_unit'        => ['nullable', 'numeric', 'min:0'],
            'lines'                      => ['required', 'array', 'min:1'],
            'lines.*.material_id'        => ['required', 'integer', Rule::exists('materials', 'id')],
            'lines.*.quantity_per_unit'  => ['required', 'numeric', 'min:0.0001'],
            'lines.*.waste_pct'          => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
