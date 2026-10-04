<?php

namespace Modules\Tenant\Http\Requests\Concerns;

use Modules\Tenant\Models\TenantAddress;

/**
 * Save/Store/UpdateTenantRequest ortak kuralları: `addresses.{billing|shipping|return}`.
 * Adres metni boş gelen tür silinir (bkz. TenantService::syncAddresses); dolu gelirse il/ilçe
 * zorunlu — Bagisto müşteri adresi bunlarsız kaydedilemez.
 */
trait ValidatesTenantAddresses
{
    /** @return array<string, array<int, string>> */
    protected function addressRules(): array
    {
        $rules = ['addresses' => ['sometimes', 'array:'.implode(',', TenantAddress::TYPES)]];

        foreach (TenantAddress::TYPES as $type) {
            $p = "addresses.{$type}";
            $rules += [
                $p => ['nullable', 'array'],
                "{$p}.contact_name" => ['nullable', 'string', 'max:191'],
                "{$p}.phone" => ['nullable', 'string', 'max:32'],
                "{$p}.address" => ['nullable', 'string', 'max:1000'],
                "{$p}.district" => ["required_with:{$p}.address", 'nullable', 'string', 'max:100'],
                "{$p}.city" => ["required_with:{$p}.address", 'nullable', 'string', 'max:100'],
                "{$p}.postal_code" => ['nullable', 'string', 'max:16'],
                "{$p}.country" => ['nullable', 'string', 'max:100'],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function addressAttributes(): array
    {
        $names = ['contact_name' => 'yetkili', 'phone' => 'telefon', 'address' => 'adres', 'district' => 'ilçe', 'city' => 'il', 'postal_code' => 'posta kodu', 'country' => 'ülke'];
        $attributes = [];

        foreach (TenantAddress::LABELS as $type => $label) {
            foreach ($names as $field => $name) {
                $attributes["addresses.{$type}.{$field}"] = mb_strtolower($label).' '.$name;
            }
        }

        return $attributes;
    }
}
