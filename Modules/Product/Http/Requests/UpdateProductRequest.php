<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\Product;

class UpdateProductRequest extends StoreProductRequest
{
    /** Güncellenen ürünü sku/slug benzersizlik kontrolünden hariç tutar. */
    protected function ignoredProductId(): ?int
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $product->id
            : (is_numeric($product) ? (int) $product : null);
    }
}
