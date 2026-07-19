<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    /**
     * Ürün formundaki marka arama kutusundan tek isimle hızlı ekleme.
     * Aynı isimde marka zaten varsa yenisini oluşturmadan onu döner.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $name = trim($data['name']);

        $brand = Brand::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $brand) {
            $baseSlug = Str::slug($name) ?: 'marka';
            $slug = $baseSlug;
            for ($suffix = 2; Brand::where('slug', $slug)->exists(); $suffix++) {
                $slug = "{$baseSlug}-{$suffix}";
            }

            $brand = Brand::create([
                'name'       => $name,
                'slug'       => $slug,
                'sort_order' => (int) Brand::max('sort_order') + 1,
            ]);
        }

        return response()->json([
            'id'    => $brand->id,
            'slug'  => $brand->slug,
            'name'  => $brand->name,
            'label' => $brand->name,
        ]);
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
