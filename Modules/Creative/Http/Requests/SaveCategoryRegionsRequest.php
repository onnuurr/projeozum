<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Creative\Models\MockupOutfit;

/** Creative ayarlar › Mokap › Kategoriler: kategori → bölge (+ varsayılan boy); bölge boş = mokap dışı. */
class SaveCategoryRegionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mockup-settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'rows'               => ['required', 'array'],
            'rows.*.category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'rows.*.region'      => ['nullable', Rule::in(MockupOutfit::REGIONS)],
            'rows.*.length'      => ['nullable', Rule::in(MockupOutfit::LENGTHS)],
        ];
    }
}
