<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Ürün Giydirme › arka plandaki üretimleri durdur (tek sonuç, bir ürün ya da hepsi). */
class StopGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creative.asset.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Boşsa bütün üretimler durdurulur.
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
