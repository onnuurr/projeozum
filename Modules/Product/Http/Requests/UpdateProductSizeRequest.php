<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\ProductSize;

class UpdateProductSizeRequest extends StoreProductSizeRequest
{
    protected string $permission = 'variant-option.update';

    protected function ignoredId(): ?int
    {
        $size = $this->route('size');

        return $size instanceof ProductSize ? $size->id : (is_numeric($size) ? (int) $size : null);
    }
}
