<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemQtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('portal.checkout') ?? false;
    }

    public function rules(): array
    {
        return [
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
