<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ret seçim maddesi güncelleme (etiket/kategori/sıra/aktiflik).
 */
class UpdateRejectionReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('creative.rejection-reasons.manage');
    }

    public function rules(): array
    {
        return [
            'category'   => ['nullable', 'string', 'max:120'],
            'label'      => ['required', 'string', 'max:120'],
            'hint'       => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = trim((string) $this->input('category', ''));

        $this->merge([
            'category'  => $category !== '' ? $category : 'Düzeltilmesi gereken alan',
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
