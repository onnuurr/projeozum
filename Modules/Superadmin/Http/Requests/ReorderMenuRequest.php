<?php

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'items'             => ['required', 'array'],
            'items.*.id'        => ['required', 'integer', 'exists:superadmin_menus,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:superadmin_menus,id'],
            'items.*.sort_order'=> ['required', 'integer'],
        ];
    }
}
