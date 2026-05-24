<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'image'              => ['required', 'file', 'image', 'max:5120'], // 5MB
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'alt_text'           => ['nullable', 'string', 'max:191'],
            'is_cover'           => ['nullable', 'boolean'],
        ]);

        $path = $request->file('image')->store('products/' . $product->id, 'public');
        $url  = Storage::disk('public')->url($path);

        DB::transaction(function () use ($product, $data, $url) {
            $isCover = (bool) ($data['is_cover'] ?? false);

            if ($isCover) {
                $product->images()->update(['is_cover' => false]);
            }

            $sortOrder = (int) $product->images()->max('sort_order') + 1;

            $product->images()->create([
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'url'                => $url,
                'alt_text'           => $data['alt_text'] ?? null,
                'sort_order'         => $sortOrder,
                'is_cover'           => $isCover,
            ]);
        });

        return back()->with('success', 'Görsel yüklendi.');
    }

    public function update(Request $request, ProductImage $image): RedirectResponse
    {
        $data = $request->validate([
            'alt_text'   => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_cover'   => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($image, $data) {
            if (! empty($data['is_cover'])) {
                ProductImage::query()
                    ->where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_cover' => false]);
            }
            $image->update($data);
        });

        return back()->with('success', 'Görsel güncellendi.');
    }

    public function destroy(ProductImage $image): RedirectResponse
    {
        // url'den disk path'ini çıkar; sadece /storage/ ile başlayan local kayıtları sil
        $publicBase = '/storage/';
        if (str_starts_with($image->url, $publicBase)) {
            $diskPath = substr($image->url, strlen($publicBase));
            Storage::disk('public')->delete($diskPath);
        }

        $image->delete();

        return back()->with('success', 'Görsel silindi.');
    }
}
