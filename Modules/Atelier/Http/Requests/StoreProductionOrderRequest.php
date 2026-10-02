<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.production.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id'                 => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouse_id'               => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'planned_qty'                => ['required', 'integer', 'min:1'],
            'due_date'                   => ['nullable', 'date'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('product_variants', 'id')->where('product_id', $this->integer('product_id')),
            ],
            'items.*.planned_qty'        => ['required', 'integer', 'min:0'],
            'save_as_product_route'      => ['boolean'],
        ] + UpdateProductRouteRequest::stepRules('steps');
    }

    public function messages(): array
    {
        return UpdateProductRouteRequest::stepMessages('steps');
    }
}
