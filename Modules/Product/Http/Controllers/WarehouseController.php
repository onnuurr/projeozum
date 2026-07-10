<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\StoreWarehouseRequest;
use Modules\Product\Http\Requests\UpdateWarehouseRequest;
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

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        Warehouse::create($request->validated());

        return redirect()->route('products.warehouses.index')
            ->with('success', 'Depo eklendi.');
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

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
}
