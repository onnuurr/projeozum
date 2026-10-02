<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\Product;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Ürün SKU'su oluşturulduktan sonra değiştirilemez (slug gibi): Bagisto ürünleri SKU ile
     * eşleştirir — değişen SKU mağazada yeni ürün açar, eskisi yetim kalır ve ona gelen
     * sipariş "eşleşmeyen SKU" olur. Varyant SKU'ları ProductService'te SKU anahtarıyla
     * eşleşir; "yeniden adlandırma" sil+ekle demektir ve silme korumasına tabidir.
     */
    public function rules(): array
    {
        $rules   = parent::rules();
        $product = $this->route('product');

        if ($product instanceof Product) {
            $rules['sku'][] = function (string $attribute, mixed $value, \Closure $fail) use ($product): void {
                if ((string) $value !== $product->sku) {
                    $fail(__('product::messages.sku_locked'));
                }
            };
        }

        return $rules;
    }

    /** Güncellenen ürünü sku/slug benzersizlik kontrolünden hariç tutar. */
    protected function ignoredProductId(): ?int
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $product->id
            : (is_numeric($product) ? (int) $product : null);
    }
}
