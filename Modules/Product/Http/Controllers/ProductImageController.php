<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
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
            'image'              => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'], // 5MB (webp dahil)
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'alt_text'           => ['nullable', 'string', 'max:191'],
            'is_cover'           => ['nullable', 'boolean'],
        ]);

        $path = $request->file('image')->store('products/' . $product->id, Media::disk());

        DB::transaction(function () use ($product, $data, $path) {
            $isCover = (bool) ($data['is_cover'] ?? false);

            if ($isCover) {
                $product->images()->update(['is_cover' => false]);
            }

            $sortOrder = (int) $product->images()->max('sort_order') + 1;

            $product->images()->create([
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'path'               => $path,
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

        // Flash basılmıyor — ProductForm.vue setCover() onSuccess'te kendi toast'unu gösteriyor.
        return back();
    }

    public function destroy(ProductImage $image): RedirectResponse
    {
        // DB'de relative path tutulur; aktif medya diskinden doğrudan silinir.
        if ($image->path) {
            Storage::disk(Media::disk())->delete($image->path);
        }

        $image->delete();

        // Flash basılmıyor — ProductForm.vue removeExistingImage() onSuccess'te kendi toast'unu gösteriyor.
        return back();
    }
}
