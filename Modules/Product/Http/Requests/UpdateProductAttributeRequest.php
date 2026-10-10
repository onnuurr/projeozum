<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\ProductAttribute;

/** Kod ve tip oluşturulduktan sonra değişmez; gönderilse de yok sayılır. */
class UpdateProductAttributeRequest extends StoreProductAttributeRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product-attribute.update') ?? false;
    }

    public function rules(): array
    {
        return $this->commonRules();
    }

    protected function optionType(): ?string
    {
        return $this->currentAttribute()?->type;
    }

    protected function currentAttribute(): ?ProductAttribute
    {
        $attribute = $this->route('attribute');

        return $attribute instanceof ProductAttribute ? $attribute : null;
    }
}
