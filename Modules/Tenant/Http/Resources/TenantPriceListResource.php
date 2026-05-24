<?php

namespace Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantPriceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'tenant_id'        => $this->tenant_id,
            'product_group_id' => $this->product_group_id,
            'discount_rate'    => (float) $this->discount_rate,
            'special_price'    => $this->special_price ? (float) $this->special_price : null,
            'valid_from'       => $this->valid_from?->toIso8601String(),
            'valid_until'      => $this->valid_until?->toIso8601String(),
            'is_active'        => $this->is_active,
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }
}
