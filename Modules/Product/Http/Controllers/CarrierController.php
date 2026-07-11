<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Http\Requests\StoreCarrierRequest;
use Modules\Product\Http\Requests\UpdateCarrierRequest;
use Modules\Product\Models\Carrier;

class CarrierController extends Controller
{
    public function index(): Response
    {
        $carriers = Carrier::query()
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Carrier $c) => [
                'id'                    => $c->id,
                'code'                  => $c->code,
                'name'                  => $c->name,
                'tracking_url_template' => $c->tracking_url_template,
                'is_active'             => $c->is_active,
                'sort_order'            => $c->sort_order,
                'orderCount'            => $c->orders_count,
                'updatedAt'             => optional($c->updated_at)->format('Y-m-d'),
            ]);

        return Inertia::render('Product::Carriers', [
            'carriers' => $carriers,
        ]);
    }

    public function store(StoreCarrierRequest $request): RedirectResponse
    {
        Carrier::create($request->validated());

        return redirect()->route('carriers.index')
            ->with('success', 'Kargo firması eklendi.');
    }

    public function update(UpdateCarrierRequest $request, Carrier $carrier): RedirectResponse
    {
        $carrier->update($request->validated());

        return redirect()->route('carriers.index')
            ->with('success', 'Kargo firması güncellendi.');
    }

    public function destroy(Carrier $carrier): RedirectResponse
    {
        // Geçmiş siparişlere bağlıysa silme; is_active=false ile pasifleştir.
        if ($carrier->orders()->exists()) {
            return back()->withErrors([
                'carrier' => 'Bu kargo firmasına bağlı siparişler var. Silmek yerine pasife alın.',
            ]);
        }

        $carrier->delete();

        return redirect()->route('carriers.index')
            ->with('success', 'Kargo firması silindi.');
    }
}
