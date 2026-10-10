<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Köşe detayının düzeltilmesi: fotoğraf + kutu (0..1) ya da off = detayı kaldır (bkz. MockupDetailInset). */
class UpdateDetailInsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creative.asset.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'off'    => ['sometimes', 'boolean'],
            'photo'  => ['required_unless:off,true', 'nullable', 'string', 'max:500'],
            'box'    => ['required_unless:off,true', 'nullable', 'array', 'size:4'],
            'box.*'  => ['numeric', 'between:0,1'],
        ];
    }

    public function after(): array
    {
        return [function ($v) {
            $b = $this->input('box');
            if (! $this->boolean('off') && is_array($b) && count($b) === 4 && ($b[2] - $b[0] < 0.02 || $b[3] - $b[1] < 0.02)) {
                $v->errors()->add('box', 'Detay kutusu çok küçük.');
            }
        }];
    }
}
