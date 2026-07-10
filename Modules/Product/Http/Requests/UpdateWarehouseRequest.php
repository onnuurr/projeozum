<?php

namespace Modules\Product\Http\Requests;

use Modules\Product\Models\Warehouse;

class UpdateWarehouseRequest extends StoreWarehouseRequest
{
    protected function ignoredWarehouseId(): ?int
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            ? $warehouse->id
            : (is_numeric($warehouse) ? (int) $warehouse : null);
    }
}
