<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Http\Requests\StoreDevelopmentProductRequest;
use Modules\Atelier\Models\FasonSupplier;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Services\DevelopmentProductService;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttributeOption;
use Modules\Product\Models\Category;

/**
 * "Üretilecek Ürün Ekle": reçete + ön maliyet + kâr oranıyla geçici stok kartı açar.
 * (Hazır ürün için Product modülünün /products/create akışı kullanılır.)
 */
class DevelopmentProductController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Atelier::DevelopmentProductForm', [
            'categories' => Category::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'brands'     => Brand::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            // Cinsiyet seçenekleri Ürün Ayarları → Özellikler'deki `cinsiyet` özelliğinden
            'genderOptions' => ProductAttributeOption::query()
                ->whereHas('attribute', fn ($q) => $q->where('code', Product::GENDER_ATTRIBUTE))
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->map(fn (ProductAttributeOption $o) => ['value' => $o->id, 'label' => $o->name]),
            // Ön maliyet istemcide canlı hesaplanır; sunucu kayıtta BomService::unitCostFor ile yeniden hesaplar.
            'materials'  => Material::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'unit', 'unit_cost'])
                ->map(fn (Material $m) => [
                    'id'       => $m->id,
                    'code'     => $m->code,
                    'name'     => $m->name,
                    'unit'     => $m->unit,
                    'unitCost' => (float) $m->unit_cost,
                ]),
            // Opsiyonel üretim rotası: operasyonlar sort_order sırasıyla önerilir.
            'operations'     => Operation::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'default_location', 'default_unit_cost']),
            'fasonSuppliers' => FasonSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreDevelopmentProductRequest $request, DevelopmentProductService $service): RedirectResponse
    {
        $product = $service->create($request->validated());

        return redirect()
            ->route('atelier.production-orders.index', ['product_id' => $product->id])
            ->with('success', 'Geçici stok kartı açıldı. İlk iş emri tamamlanınca ürüne dönüşecek.');
    }
}
