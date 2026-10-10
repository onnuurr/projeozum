<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Product\Http\Requests\BulkDestroyVariantOptionRequest;
use Modules\Product\Http\Requests\BulkStoreVariantOptionRequest;
use Modules\Product\Http\Requests\StoreProductColorRequest;
use Modules\Product\Http\Requests\StoreProductSizeRequest;
use Modules\Product\Http\Requests\UpdateProductColorRequest;
use Modules\Product\Http\Requests\UpdateProductSizeRequest;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Modules\Product\Services\VariantOptionService;

/**
 * Ürün formundaki beden/renk seçim listelerinin yazma işlemleri (variant-option.* yetkileri).
 * Liste ekranı Product Ayarları'ndaki Bedenler/Renkler sekmeleridir (ProductSettingsController).
 * Yeniden adlandırma o değeri kullanan varyantlara da yansır (bkz. VariantOptionService).
 */
class VariantOptionController extends Controller
{
    public function __construct(private readonly VariantOptionService $options) {}

    public function storeSize(StoreProductSizeRequest $request): RedirectResponse
    {
        $this->options->createSize($request->validated());

        return $this->back('sizes');
    }

    public function updateSize(UpdateProductSizeRequest $request, ProductSize $size): RedirectResponse
    {
        $this->options->updateSize($size, $request->validated());

        return $this->back('sizes');
    }

    public function destroySize(ProductSize $size): RedirectResponse
    {
        $this->options->delete($size);

        return $this->back('sizes');
    }

    public function storeColor(StoreProductColorRequest $request): RedirectResponse
    {
        $this->options->createColor($request->validated());

        return $this->back('colors');
    }

    public function updateColor(UpdateProductColorRequest $request, ProductColor $color): RedirectResponse
    {
        $this->options->updateColor($color, $request->validated());

        return $this->back('colors');
    }

    public function destroyColor(ProductColor $color): RedirectResponse
    {
        $this->options->delete($color);

        return $this->back('colors');
    }

    /** Virgülle girilen bedenleri/renkleri toplu ekler ({type}: sizes | colors). */
    public function bulkStore(BulkStoreVariantOptionRequest $request, string $type): RedirectResponse
    {
        $this->options->bulkCreate($type, $request->validated('items'));

        return $this->back($type);
    }

    /** Seçilen bedenleri/renkleri toplu siler ({type}: sizes | colors). */
    public function bulkDestroy(BulkDestroyVariantOptionRequest $request, string $type): RedirectResponse
    {
        $this->options->bulkDelete($type, $request->validated('ids'));

        return $this->back($type);
    }

    private function back(string $tab): RedirectResponse
    {
        return redirect()->route('products.settings.index', ['tab' => $tab]);
    }
}
