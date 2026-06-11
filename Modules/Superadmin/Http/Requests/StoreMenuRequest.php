<?php

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'parent_id'  => ['nullable', 'integer', 'exists:superadmin_menus,id'],
            'label'      => ['required', 'string', 'max:100'],
            'icon'       => ['nullable', 'string', 'max:64'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url'        => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'exists:permissions,name'],
            'sort_order' => ['nullable', 'integer'],
            'is_active'  => ['boolean'],
        ];
    }
}
