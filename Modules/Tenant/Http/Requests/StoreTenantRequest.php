<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tenant\Http\Requests\Concerns\ValidatesTenantAddresses;
use Modules\Tenant\Rules\ValidTaxNumber;

class StoreTenantRequest extends FormRequest
{
    use ValidatesTenantAddresses;

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
            'tax_number'       => ['nullable', 'string', 'max:32', new ValidTaxNumber()],
            'tax_office'       => 'nullable|string|max:191',
            'email'            => 'nullable|email|max:191',
            'phone'            => 'nullable|string|max:32',
            'contact_person'   => 'nullable|string|max:191',
            'contact_phone'    => 'nullable|string|max:32',
            'credit_limit'     => 'nullable|numeric|min:0',
            'discount_rate'    => 'nullable|numeric|min:0|max:100',
            'is_active'        => 'boolean',
            'notes'            => 'nullable|string',
            ...$this->addressRules(),
        ];
    }

    public function attributes(): array
    {
        return $this->addressAttributes();
    }
}
