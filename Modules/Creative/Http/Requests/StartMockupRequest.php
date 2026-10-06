<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Ürün Giydirme › tek ürün için mokap başlat (ücretli AI; günlük limit MockupSettings'te). */
class StartMockupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creative.asset.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            // Boşsa ürünün cinsiyetine göre varsayılan manken (Creative ayarlar › Mokap).
            'mannequin_id' => ['nullable', 'integer', 'exists:creative_mannequins,id'],
        ];
    }
}
