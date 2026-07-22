<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Creative\Models\RejectionReason;

/**
 * Ret seçim maddesi ekleme. Yetki route middleware'i (can:creative.rejection-reasons.manage)
 * ile aynı izne bağlıdır; burada tekrar doğrulanır (superadmin Gate::before ile geçer).
 */
class StoreRejectionReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('creative.rejection-reasons.manage');
    }

    public function rules(): array
    {
        return [
            'category'   => ['nullable', 'string', 'max:120'],
            'context'    => ['nullable', Rule::in(array_keys(RejectionReason::CONTEXTS))],
            'label'      => ['required', 'string', 'max:120'],
            'hint'       => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = trim((string) $this->input('category', ''));
        $context  = trim((string) $this->input('context', ''));

        $this->merge([
            'category'  => $category !== '' ? $category : 'Düzeltilmesi gereken alan',
            'context'   => $context !== '' ? $context : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
