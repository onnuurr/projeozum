<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('tenant.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'code'             => 'required|string|max:32|unique:tenants,code',
            'name'             => 'required|string|max:191',
            'slug'             => 'nullable|string|max:191|unique:tenants,slug',
            'legal_name'       => 'nullable|string|max:255',
            'tenant_type_id'   => 'nullable|exists:tenant_types,id',
            'tax_number'       => 'nullable|string|max:32',
            'tax_office'       => 'nullable|string|max:191',
            'email'            => 'nullable|email|max:191',
            'phone'            => 'nullable|string|max:32',
            'contact_person'   => 'nullable|string|max:191',
            'contact_phone'    => 'nullable|string|max:32',
            'address'          => 'nullable|string',
            'city'             => 'nullable|string|max:100',
            'district'         => 'nullable|string|max:100',
            'country'          => 'nullable|string|max:100',
            'postal_code'      => 'nullable|string|max:16',
            'credit_limit'     => 'nullable|numeric|min:0',
            'payment_term_days'=> 'nullable|integer|min:0',
            'discount_rate'    => 'nullable|numeric|min:0|max:100',
            'is_active'        => 'boolean',
            'notes'            => 'nullable|string',
        ];
    }
}
