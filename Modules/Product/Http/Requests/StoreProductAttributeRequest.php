<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\ProductAttribute;

class StoreProductAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product-attribute.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'    => mb_strtolower(trim((string) $this->input('code'))),
            'name'    => ProductAttribute::cleanName($this->input('name')),
            'options' => collect((array) $this->input('options', []))
                ->map(fn ($o) => ['id' => $o['id'] ?? null, 'name' => ProductAttribute::cleanName($o['name'] ?? ''), 'icon' => ($o['icon'] ?? null) ?: null])
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            // Bagisto attribute kodu: küçük harf/rakam/alt çizgi, harfle başlar
            'code'           => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::notIn(ProductAttribute::RESERVED_CODES), Rule::unique('product_attributes', 'code')],
            'type'           => ['required', Rule::in(ProductAttribute::TYPES)],
            ...$this->commonRules(),
        ];
    }

    /** Store/Update ortak: kod ve tip yalnız oluştururken alınır. */
    protected function commonRules(): array
    {
        $hasOptions = in_array($this->optionType(), ProductAttribute::OPTION_TYPES, true);

        return [
            'name'           => ['bail', 'required', 'string', 'max:191', $this->uniqueNameRule()],
            'is_filterable'  => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'options'        => $hasOptions ? ['required', 'array', 'min:1'] : ['nullable', 'array', 'max:0'],
            'options.*.id'   => ['nullable', 'integer'],
            'options.*.name' => ['bail', 'required', 'string', 'max:191', $this->distinctOptionRule()],
            'options.*.icon' => ['nullable', 'string', 'max:64', 'regex:/^(?:(?:lab|oz):)?[a-z0-9-]+$/'], // ortak ikon kaydındaki ad
        ];
    }

    protected function optionType(): ?string
    {
        return $this->input('type');
    }

    /** Güncellenen özellik (Store'da yok); ad benzersizliğinde kendisi hariç tutulur. */
    protected function currentAttribute(): ?ProductAttribute
    {
        return null;
    }

    /** Özellik adı, harf/boşluk farkı gözetmeden (Türkçe İ/ı dahil) tektir. */
    private function uniqueNameRule(): \Closure
    {
        return function (string $field, mixed $value, \Closure $fail): void {
            $key = ProductAttribute::nameKey($value);
            $clash = ProductAttribute::query()
                ->when($this->currentAttribute(), fn ($q, ProductAttribute $a) => $q->whereKeyNot($a->id))
                ->get(['id', 'code', 'name'])
                ->first(fn (ProductAttribute $a) => ProductAttribute::nameKey($a->name) === $key);

            if ($clash) {
                $fail(__('product::messages.validation.attribute_name_taken', ['name' => $clash->name, 'code' => $clash->code]));
            }
        };
    }

    /** Aynı özellikte iki seçenek aynı ada (harf/boşluk farkı gözetmeden) sahip olamaz; hata sonraki kopyaya yazılır. */
    private function distinctOptionRule(): \Closure
    {
        return function (string $field, mixed $value, \Closure $fail): void {
            $index = (int) explode('.', $field)[1];
            $key = ProductAttribute::nameKey($value);
            $earlier = array_slice((array) $this->input('options', []), 0, $index);

            if (collect($earlier)->contains(fn ($o) => ProductAttribute::nameKey($o['name'] ?? '') === $key)) {
                $fail(__('product::messages.validation.attribute_option_duplicate', ['name' => $value]));

                return;
            }

            // Tutulan başka bir mevcut seçeneğin şu anki adına geçilemez (Yaz↔Kış takası): DB unique'ine
            // takılır, Bagisto tarafında da ad eşleşmesiyle iki seçenek birleşir.
            $attribute = $this->currentAttribute();
            if (! $attribute) {
                return;
            }
            $ownId = (int) ($this->input("options.{$index}.id") ?? 0);
            $keptIds = collect((array) $this->input('options', []))->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $holder = $attribute->options()->whereIn('id', $keptIds)->whereKeyNot($ownId)->get(['id', 'name'])
                ->first(fn ($o) => ProductAttribute::nameKey($o->name) === $key);

            if ($holder) {
                $fail(__('product::messages.validation.attribute_option_name_held', ['name' => $holder->name]));
            }
        };
    }

    public function messages(): array
    {
        return [
            'code.regex'              => __('product::messages.validation.attribute_code_format'),
            'code.not_in'             => __('product::messages.validation.attribute_code_reserved'),
            'code.unique'             => __('product::messages.validation.attribute_code_taken'),
            'options.required'        => __('product::messages.validation.attribute_options_required'),
        ];
    }
}
