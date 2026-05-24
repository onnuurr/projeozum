<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
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

    public function store(Request $request): RedirectResponse
    {
        Brand::create($this->validateBrand($request));

        return redirect()->route('products.brands.index')
            ->with('success', 'Marka eklendi.');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validateBrand($request, $brand->id));

        return redirect()->route('products.brands.index')
            ->with('success', 'Marka güncellendi.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->withErrors([
                'brand' => 'Bu markaya bağlı ürünler var, önce ürünlerin markasını değiştirin.',
            ]);
        }

        $brand->delete();

        return redirect()->route('products.brands.index')
            ->with('success', 'Marka silindi.');
    }

    private function validateBrand(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:191'],
            'slug'       => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('brands', 'slug')->ignore($ignoreId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.regex' => 'Slug yalnızca küçük harf, rakam ve tire içerebilir.',
        ]);
    }
}
