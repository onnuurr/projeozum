<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\ProductColor;

class UpdateProductColorRequest extends StoreProductColorRequest
{
    protected string $permission = 'variant-option.update';

    protected function ignoredId(): ?int
    {
        $color = $this->route('color');

        return $color instanceof ProductColor ? $color->id : (is_numeric($color) ? (int) $color : null);
    }
}
