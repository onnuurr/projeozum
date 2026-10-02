<?php

namespace Modules\Bagisto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Bagisto\Models\DealerAgreement;

/** Bayi Sözleşmeleri: bir sözleşmenin yeni sürümünü yayımlar (DealerAgreementController::publish). */
class PublishDealerAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('dealer-agreement.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:200000'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'locale' => ['nullable', Rule::in(array_keys(DealerAgreement::LOCALES))],
            // Mevcut bayilerden portala girişte yeniden onay iste.
            'requires_acceptance' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('bagisto::messages.agreements.body_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'başlık',
            'body' => 'metin',
            'change_note' => 'değişiklik notu',
        ];
    }
}
