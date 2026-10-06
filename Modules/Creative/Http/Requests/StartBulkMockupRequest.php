<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Ürün Giydirme › mokap bekleyen ürünler için toplu başlat (en fazla günlük kalan hak kadar). */
class StartBulkMockupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creative.asset.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
