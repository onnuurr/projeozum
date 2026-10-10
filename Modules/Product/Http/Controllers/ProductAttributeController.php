<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Product\Http\Requests\BulkDestroyProductAttributeRequest;
use Modules\Product\Http\Requests\StoreProductAttributeRequest;
use Modules\Product\Http\Requests\SyncCategoryAttributesRequest;
use Modules\Product\Http\Requests\UpdateProductAttributeRequest;
use Modules\Product\Models\Category;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Services\ProductAttributeAdminService;

/**
 * Ürün Ayarları → Özellikler sekmesinin yazma işlemleri (product-attribute.* yetkileri) ve
 * Kategoriler ekranındaki özellik ataması (category.manage). Liste ProductSettingsController'da.
 */
class ProductAttributeController extends Controller
{
    public function __construct(private readonly ProductAttributeAdminService $attributes) {}

    public function store(StoreProductAttributeRequest $request): RedirectResponse
    {
        $this->attributes->create($request->validated());

        return $this->back();
    }

    public function update(UpdateProductAttributeRequest $request, ProductAttribute $attribute): RedirectResponse
    {
        $this->attributes->update($attribute, $request->validated());

        return $this->back();
    }

    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        $this->attributes->delete($attribute);

        return $this->back();
    }

    public function bulkDestroy(BulkDestroyProductAttributeRequest $request): RedirectResponse
    {
        $this->attributes->bulkDelete($request->validated('ids'));

        return $this->back();
    }

    public function syncCategory(SyncCategoryAttributesRequest $request, Category $category): RedirectResponse
    {
        $this->attributes->syncCategory($category, $request->validated('attributes'));

        return redirect()->route('products.categories.index');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('products.settings.index', ['tab' => 'attributes']);
    }
}
