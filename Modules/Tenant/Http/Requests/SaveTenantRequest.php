<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Rules\ValidTaxNumber;

/**
 * Tenants.vue (Inertia) oluşturma/düzenleme formu — hem store hem update.
 * API tarafı Store/UpdateTenantRequest'i kullanır; owner hesabı ve kargo
 * anlaşması alanları yalnızca bu formda var.
 */
class SaveTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('tenant.manage') ?? false;
    }

    public function rules(): array
    {
        // Owner hesabı oluştururken zorunlu; düzenlemede 'sometimes' — alanlar dolu
        // gelirse (bkz. Tenants.vue) mevcut owner kullanıcısı güncellenir
        // (bkz. TenantService::update, TenantUserService::updateOwner).
        $tenant = $this->tenant();
        $isCreate = $tenant === null;
        $ownerId = $tenant?->owner?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('tenants', 'code')->ignore($tenant?->id),
            ],
            'name' => ['required', 'string', 'max:191'],
            'owner_name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:191'],
            'owner_email' => [$isCreate ? 'required' : 'sometimes', 'email', 'max:191', Rule::unique('users', 'email')->ignore($ownerId)],
            'owner_password' => [$isCreate ? 'required' : 'sometimes', 'string', 'min:8', 'confirmed'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tenant_type_id' => ['nullable', 'integer', 'exists:tenant_types,id'],
            'tax_number' => ['nullable', 'string', 'max:32', new ValidTaxNumber],
            'tax_office' => ['nullable', 'string', 'max:191'],
            'tenant_application_id' => ['nullable', 'integer', 'exists:tenant_applications,id'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipping_agreement_type' => ['nullable', 'in:own,platform'],
            'carrier_id' => ['required_if:shipping_agreement_type,own', 'nullable', 'integer', 'exists:carriers,id'],
            // Ham onay bayrağı — DB kolonu değil, tenantData() içinde shipping_terms_accepted_at'e çevrilir.
            'shipping_terms_accepted' => ['accepted_if:shipping_agreement_type,platform'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'İş ortağı kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
            'owner_email.unique' => 'Bu e-posta adresiyle zaten bir kullanıcı hesabı var.',
            'carrier_id.required_if' => 'İş ortağı kendi kargo anlaşmasını kullanıyorsa kargo firması seçilmelidir.',
            'shipping_terms_accepted.accepted_if' => 'Platform kargo anlaşması seçildiyse şartların kabul edildiği onaylanmalı.',
        ];
    }

    /**
     * TenantService'e verilecek veri: validated() eksi form-only alanlar
     * (tenant_application_id, shipping_terms_accepted), artı kargo onay damgası.
     */
    public function tenantData(): array
    {
        $data = $this->validated();
        unset($data['tenant_application_id']);

        return $this->applyShippingAgreementTimestamp($data);
    }

    public function applicationId(): ?int
    {
        $id = $this->validated('tenant_application_id');

        return $id ? (int) $id : null;
    }

    /**
     * `shipping_terms_accepted` ham onay bayrağını `shipping_terms_accepted_at`
     * damgasına çevirir; `own` seçilmişse ya da alan hiç seçilmemişse temizler.
     * Personel bu kutuyu, elinde SaaS-side imzalı/kabul edilmiş bir onay
     * olduğunu beyan ederek işaretler (bkz. proje planı: staff-checkbox yeterli).
     */
    private function applyShippingAgreementTimestamp(array $data): array
    {
        $accepted = (bool) ($data['shipping_terms_accepted'] ?? false);
        unset($data['shipping_terms_accepted']);

        if (($data['shipping_agreement_type'] ?? null) !== 'platform') {
            $data['shipping_terms_accepted_at'] = null;

            return $data;
        }

        $data['shipping_terms_accepted_at'] = $accepted
            ? ($this->tenant()?->shipping_terms_accepted_at ?? now())
            : null;

        return $data;
    }

    private function tenant(): ?Tenant
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
