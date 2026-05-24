<?php

namespace Modules\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'tenant_id'  => $this->tenant_id,
            'order_id'   => $this->order_id,
            'amount'     => (float) $this->amount,
            'currency'   => $this->currency,
            'status'     => $this->status,
            'due_date'   => $this->due_date?->toIso8601String(),
            'paid_at'    => $this->paid_at?->toIso8601String(),
            'note'       => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
