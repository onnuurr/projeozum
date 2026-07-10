<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\Brand;

class UpdateBrandRequest extends StoreBrandRequest
{
    protected function ignoredBrandId(): ?int
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand ? $brand->id : (is_numeric($brand) ? (int) $brand : null);
    }
}
