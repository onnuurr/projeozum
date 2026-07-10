<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Product\Http\Requests\StorePriceListRequest;
use Modules\Product\Http\Requests\UpdatePriceListRequest;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\ProductVariant;

class PriceListController extends Controller
{
    public function store(StorePriceListRequest $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validated();

        // (variant_id, type) unique — varsa güncelle, yoksa oluştur
        PriceList::updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'type'               => $data['type'],
            ],
            [
                'price'     => $data['price'],
                'currency'  => $data['currency'] ?? 'TRY',
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return back()->with('success', 'Fiyat listesi güncellendi.');
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        $priceList->update($request->validated());

        return back()->with('success', 'Fiyat güncellendi.');
    }

    public function destroy(PriceList $priceList): RedirectResponse
    {
        // Karar: deaktivasyon yerine kalıcı sil — unique constraint serbest kalsın.
        $priceList->forceDelete();

        return back()->with('success', 'Fiyat listesi silindi.');
    }
}
