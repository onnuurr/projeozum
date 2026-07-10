<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\StockMovement;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'warehouse_id'       => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'type'               => ['required', Rule::in([
                StockMovement::TYPE_IN,
                StockMovement::TYPE_OUT,
                StockMovement::TYPE_ADJUSTMENT,
            ])],
            'quantity'           => ['required', 'integer', 'not_in:0'],
            'note'               => ['nullable', 'string', 'max:1000'],
        ];
    }
}
