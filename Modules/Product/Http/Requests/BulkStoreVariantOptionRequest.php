<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ürün Ayarları › Bedenler / Renkler toplu ekleme (virgülle birden çok giriş). Tablo, rotadaki
 * {type} ile seçilir; kurallar tekli Store<ProductSize|ProductColor>Request ile aynıdır.
 */
class BulkStoreVariantOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('variant-option.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(fn ($item) => is_array($item) ? array_filter([
                'name' => trim((string) ($item['name'] ?? '')),
                'hex' => isset($item['hex']) ? strtolower(trim((string) $item['hex'])) : null,
            ], fn ($v) => $v !== null) : $item)
            ->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        $isColor = $this->isColor();
        $table = $isColor ? 'product_colors' : 'product_sizes';

        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:'.($isColor ? 100 : 50), 'distinct:ignore_case', Rule::unique($table, 'name')],
            'items.*.hex' => $isColor ? ['required', 'regex:/^#[0-9a-f]{6}$/'] : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.hex.regex' => __('product::messages.validation.hex_format'),
            'items.*.name.unique' => __('product::messages.validation.variant_option_exists'),
            'items.*.name.distinct' => __('product::messages.validation.variant_option_duplicate'),
        ];
    }

    public function attributes(): array
    {
        return ['items.*.name' => $this->isColor() ? 'renk adı' : 'beden adı'];
    }

    public function isColor(): bool
    {
        return $this->route('type') === 'colors';
    }
}
