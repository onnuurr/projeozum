<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\ProductVariant;

class PriceListController extends Controller
{
    public function store(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'type'      => ['required', Rule::in([
                PriceList::TYPE_RETAIL,
                PriceList::TYPE_DEALER,
                PriceList::TYPE_DROPSHIP,
            ])],
            'price'     => ['required', 'numeric', 'min:0'],
            'currency'  => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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

    public function update(Request $request, PriceList $priceList): RedirectResponse
    {
        $data = $request->validate([
            'price'     => ['required', 'numeric', 'min:0'],
            'currency'  => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $priceList->update($data);

        return back()->with('success', 'Fiyat güncellendi.');
    }

    public function destroy(PriceList $priceList): RedirectResponse
    {
        // Karar: deaktivasyon yerine kalıcı sil — unique constraint serbest kalsın.
        $priceList->forceDelete();

        return back()->with('success', 'Fiyat listesi silindi.');
    }
}
