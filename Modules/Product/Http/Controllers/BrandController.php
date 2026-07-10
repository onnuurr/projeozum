<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\StoreBrandRequest;
use Modules\Product\Http\Requests\UpdateBrandRequest;
use Modules\Product\Models\Brand;

class BrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $b) => [
                'id'           => $b->id,
                'name'         => $b->name,
                'slug'         => $b->slug,
                'sort_order'   => $b->sort_order,
                'productCount' => $b->products_count,
                'updatedAt'    => optional($b->updated_at)->format('Y-m-d'),
            ]);

        return Inertia::render('Product::Brands', [
            'brands' => $brands,
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        Brand::create($request->validated());

        // Toast'ı frontend (Brands.vue onSuccess) gösterir; backend flash eklemek
        // global flash→toast izleyiciyle çift toast'a yol açar.
        return redirect()->route('products.brands.index');
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated());

        return redirect()->route('products.brands.index');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->withErrors([
                'brand' => 'Bu markaya bağlı ürünler var, önce ürünlerin markasını değiştirin.',
            ]);
        }

        $brand->delete();

        return redirect()->route('products.brands.index');
    }
}
