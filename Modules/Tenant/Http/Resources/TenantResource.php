<?php

namespace Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'legal_name'       => $this->legal_name,
            'type'             => $this->whenLoaded('type', fn () => [
                'id'   => $this->type->id,
                'name' => $this->type->name,
                'code' => $this->type->code,
            ]),
            'tax_number'       => $this->tax_number,
            'tax_office'       => $this->tax_office,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'contact_person'   => $this->contact_person,
            'address'          => $this->address,
            'city'             => $this->city,
            'country'          => $this->country,
            'logo_path'        => $this->logo_path,
            'credit_limit'     => (float) $this->credit_limit,
            'current_balance'  => (float) $this->current_balance,
            'available_credit' => $this->available_credit,
            'discount_rate'    => (float) $this->discount_rate,
            'payment_term_days'=> $this->payment_term_days,
            'is_active'        => $this->is_active,
            'activated_at'     => $this->activated_at?->toIso8601String(),
            'user_count'       => $this->whenLoaded('users', fn () => $this->users->count()),
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }
}
