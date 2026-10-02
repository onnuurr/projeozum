<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.production.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct'],
            'items.*.qty'                => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, int>  product_variant_id → adet */
    public function quantities(): array
    {
        return collect($this->validated()['items'])
            ->mapWithKeys(fn (array $row) => [(int) $row['product_variant_id'] => (int) $row['qty']])
            ->all();
    }
}
