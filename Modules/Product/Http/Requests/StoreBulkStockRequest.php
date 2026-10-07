<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\StockOperation;

/**
 * Toplu stok işlemi (bkz. StockService::bulk()). Giriş/çıkışta 0 miktarlı satırlar gönderilmez
 * (arayüz süzer); sayımda 0 geçerlidir (stok sıfırlanır).
 */
class StoreBulkStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.manage') ?? false;
    }

    public function rules(): array
    {
        $min = $this->input('mode') === StockOperation::MODE_COUNT ? 0 : 1;

        return [
            'warehouse_id'       => ['required', 'integer', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'mode'               => ['required', Rule::in(StockOperation::MODES)],
            'note'               => ['nullable', 'string', 'max:1000'],
            'lines'              => ['required', 'array', 'min:1', 'max:1000'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')],
            'lines.*.quantity'   => ['required', 'integer', "min:{$min}", 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return ['lines' => 'varyant listesi', 'lines.*.quantity' => 'miktar', 'warehouse_id' => 'depo'];
    }
}
