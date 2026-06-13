<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductMarketplaceListing;

class ProductMarketplaceListingController extends Controller
{
    public function show(Product $product, string $marketplace): JsonResponse
    {
        $mp = Marketplace::where('key', $marketplace)->firstOrFail();

        $product->load([
            'category:id,name,slug,parent_id',
            'category.parent:id,name',
            'brand:id,name',
            'variants',
            'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
        ]);

        $listing = ProductMarketplaceListing::query()
            ->where('product_id', $product->id)
            ->where('marketplace_id', $mp->id)
            ->first();

        $productVariants = $product->variants->map(fn ($v) => [
            'id'    => $v->id,
            'label' => $this->variantLabel($v),
            'sku'   => $v->sku,
        ])->values()->all();

        return response()->json([
            'marketplace' => ['key' => $mp->key, 'name' => $mp->name],
            'product'     => [
                'id'          => $product->id,
                'name'        => $product->name,
                'brand'       => $product->brand?->name,
                'categoryPath' => $this->categoryPath($product),
                'price'       => (float) $product->price,
                'marketPrice' => $product->market_price !== null ? (float) $product->market_price : null,
                'status'      => 'Aktif',
                'image'       => optional($product->images->first())->url,
                'editUrl'     => "/products/{$product->id}/edit",
            ],
            'variants' => $productVariants,
            'listing'  => $this->shapeListing($listing, $product, $productVariants),
        ]);
    }

    private function shapeListing(?ProductMarketplaceListing $listing, Product $product, array $productVariants): array
    {
        $storedVariants = collect($listing?->variants ?? []);

        $variants = collect($productVariants)->map(function ($pv) use ($storedVariants) {
            $stored = $storedVariants->firstWhere('product_variant_id', $pv['id']) ?? [];
            return [
                'product_variant_id' => $pv['id'],
                'marketplace_variant' => $stored['marketplace_variant'] ?? '',
                'stock_code'          => $stored['stock_code'] ?? '',
                'barcode'             => $stored['barcode'] ?? '',
            ];
        })->values()->all();

        return [
            'id'                  => $listing?->id,
            'is_sent'             => (bool) ($listing?->is_sent ?? false),
            'sent_at'             => $listing?->sent_at?->toIso8601String(),
            'product_status'      => $listing?->product_status ?? 'active',
            'approval_status'     => $listing?->approval_status ?? 'not_sent',
            'store_name'          => $listing?->store_name ?? '',
            'model_code'          => $listing?->model_code ?? '',
            'category_path'       => $listing?->category_path ?? '',
            'title'               => $listing?->title ?? $product->name,
            'price'               => (float) ($listing?->price ?? $product->price),
            'currency'            => $listing?->currency ?? 'TL',
            'variant_extra_price' => (float) ($listing?->variant_extra_price ?? 0),
            'delivery_template'   => $listing?->delivery_template ?? '',
            'shipping_time'       => $listing?->shipping_time ?? 0,
            'variants'            => $variants,
        ];
    }

    private function variantLabel($v): string
    {
        $parts = array_filter([
            $v->color_name ? "Renk: {$v->color_name}" : null,
            $v->size ? "Beden: {$v->size}" : null,
        ]);
        return $parts === [] ? ($v->sku ?? '—') : implode(' / ', $parts);
    }

    private function categoryPath(Product $product): ?string
    {
        $cat = $product->category;
        if (! $cat) {
            return null;
        }
        return $cat->parent
            ? "{$cat->parent->name} > {$cat->name}"
            : $cat->name;
    }
}
