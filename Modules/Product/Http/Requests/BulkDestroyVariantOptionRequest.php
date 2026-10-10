<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ürün Ayarları › Bedenler / Renkler toplu silme. Tablo, rotadaki {type} ile seçilir.
 */
class BulkDestroyVariantOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('variant-option.delete') ?? false;
    }

    public function rules(): array
    {
        $table = $this->route('type') === 'colors' ? 'product_colors' : 'product_sizes';

        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', "exists:{$table},id"],
        ];
    }
}
