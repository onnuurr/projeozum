<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
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
                'lines'       => $b->lines->map(fn ($l) => [
                    'materialId'      => $l->material_id,
                    'materialName'    => $l->material?->name,
                    'unit'            => $l->material?->unit,
                    'quantityPerUnit' => (float) $l->quantity_per_unit,
                    'wastePct'        => (float) $l->waste_pct,
                ]),
            ]);

        return Inertia::render('Atelier::Boms', [
            'boms'      => $boms,
            'products'  => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'materials' => Material::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBom($request);

        DB::transaction(function () use ($data) {
            // Ürün başına tek aktif reçete: öncekini pasifle.
            ProductBom::where('product_id', $data['product_id'])->update(['is_active' => false]);

            $bom = ProductBom::create([
                'product_id' => $data['product_id'],
                'name'       => $data['name'],
                'is_active'  => true,
            ]);

            foreach ($data['lines'] as $line) {
                $bom->lines()->create([
                    'material_id'       => $line['material_id'],
                    'quantity_per_unit' => $line['quantity_per_unit'],
                    'waste_pct'         => $line['waste_pct'] ?? 0,
                ]);
            }
        });

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
            'lines'                      => ['required', 'array', 'min:1'],
            'lines.*.material_id'        => ['required', 'integer', Rule::exists('materials', 'id')],
            'lines.*.quantity_per_unit'  => ['required', 'numeric', 'min:0.0001'],
            'lines.*.waste_pct'          => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
