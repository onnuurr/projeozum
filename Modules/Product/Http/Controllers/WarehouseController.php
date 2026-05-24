<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Warehouse;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        $warehouses = Warehouse::query()
            ->withSum('stocks as total_stock', 'quantity')
            ->withCount('stocks')
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $w) => [
                'id'         => $w->id,
                'name'       => $w->name,
                'code'       => $w->code,
                'address'    => $w->address,
                'city'       => $w->city,
                'is_active'  => $w->is_active,
                'totalStock' => (int) ($w->total_stock ?? 0),
                'stockCount' => $w->stocks_count,
                'updatedAt'  => optional($w->updated_at)->format('Y-m-d'),
            ]);

        return Inertia::render('Product::Warehouses', [
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Warehouse::create($this->validateWarehouse($request));

        return redirect()->route('products.warehouses.index')
            ->with('success', 'Depo eklendi.');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validateWarehouse($request, $warehouse->id));

        return redirect()->route('products.warehouses.index')
            ->with('success', 'Depo güncellendi.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return back()->withErrors([
                'warehouse' => 'Bu depoda stok var, önce stokları başka depoya transfer edin.',
            ]);
        }

        $warehouse->delete();

        return redirect()->route('products.warehouses.index')
            ->with('success', 'Depo silindi.');
    }

    private function validateWarehouse(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:191'],
            'code'      => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('warehouses', 'code')->ignore($ignoreId),
            ],
            'address'   => ['nullable', 'string', 'max:1000'],
            'city'      => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Depo kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        ]);
    }
}
