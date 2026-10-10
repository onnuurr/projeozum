<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Product\Events\ProductImagesChanged;
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

        return back()->with('success', __('product::messages.image_uploaded'));
    }

    public function update(Request $request, ProductImage $image): RedirectResponse
    {
        $data = $request->validate([
            'alt_text'   => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_cover'   => ['nullable', 'boolean'],
            // Yalın fotoğrafın rengi (null = bütün renkler): Creative mokap o rengi bu fotoğraftan giydirir.
            'variant_colors'   => ['nullable', 'array', 'max:20'],
            'variant_colors.*' => ['string', 'max:60'],
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

    /**
     * Sürükle-bırak sıralaması: verilen sırayla sort_order 1..n yazılır, ilk görsel kapak olur
     * (düzenleme ekranı kapağı başta gösterir, Bagisto galeriyi sort_order'la alır — ikisi aynı sırada kalsın).
     * Listede olmayan görseller mevcut sıralarıyla sona eklenir. Tek görsel olayı duyurulur (tek Bagisto push'u).
     */
    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $product->id)->whereNull('deleted_at')],
        ]);

        $listed = array_map('intval', $data['ids']);
        $rest = $product->images()->whereNotIn('id', $listed)->pluck('id')->all();
        $ids = [...$listed, ...$rest];

        DB::transaction(function () use ($product, $ids) {
            ProductImage::withoutChangeAnnouncements(function () use ($product, $ids) {
                $images = ProductImage::query()->where('product_id', $product->id)->whereIn('id', $ids)->get()->keyBy('id');
                foreach ($ids as $i => $id) {
                    $images[$id]?->update(['sort_order' => $i + 1, 'is_cover' => $i === 0]);
                }
            });
            ProductImagesChanged::dispatch($product->id);
        });

        // Flash basılmıyor — ProductForm.vue saveImageOrder() kendi toast'unu gösteriyor.
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
