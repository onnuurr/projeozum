<?php

namespace Modules\Product\Services;

use App\Support\Media;
use Illuminate\Support\Collection;
use Modules\Product\Enums\Capability;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;

/**
 * Ürünleri katalog/detay/kart JSON şekline dönüştürür ve tenant fiyatlandırmasını
 * çözer. ProductController'ın okuma tarafı buraya taşındı; controller ince kalır.
 *
 * Fiyat çözümü (öncelik sırası): tenant custom_price → tenant fiyat listesi (variant)
 * → varyantın kendi fiyatı.
 */
class ProductCatalogPresenter
{
    public function __construct(
        private ProductDisplayResolver $display,
        private ProductMaterialLinkService $materialLinks,
        private ProductReadinessService $readiness,
    ) {}

    /**
     * Tenant fiyatlandırma lookup'larını batch yükler (N+1 önle).
     *
     * @param  Collection<int, Product>  $products
     * @return array{0: array<int,float>, 1: array<int,float>} [customPriceByProductId, priceListByVariantId]
     */
    public function loadTenantPricing(?Tenant $tenant, Collection $products): array
    {
        if ($tenant === null || $products->isEmpty()) {
            return [[], []];
        }

        $productIds = $products->pluck('id')->all();
        $customPriceByProduct = TenantProductAccess::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('product_id', $productIds)
            ->whereNotNull('custom_price')
            ->pluck('custom_price', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $priceListByVariant = [];
        $priceListType = $tenant->loadMissing('type')->type?->price_list_type;

        if ($priceListType) {
            $variantIds = $products->flatMap(fn (Product $p) => $p->variants->pluck('id'))->unique()->all();
            if ($variantIds !== []) {
                $priceListByVariant = PriceList::query()
                    ->whereIn('product_variant_id', $variantIds)
                    ->where('type', $priceListType)
                    ->where('is_active', true)
                    ->pluck('price', 'product_variant_id')
                    ->map(fn ($v) => (float) $v)
                    ->all();
            }
        }

        return [$customPriceByProduct, $priceListByVariant];
    }

    /**
     * Katalog listesi satırı (admin/tenant ürün tablosu). Pazaryeri eşlemeleri,
     * listeleme durumları ve görsel dizisi dahil zengin form.
     *
     * @param  array<int,float>  $customPriceByProduct
     * @param  array<int,float>  $priceListByVariant
     * @return array<string, mixed>
     */
    public function catalogRow(Product $p, array $customPriceByProduct = [], array $priceListByVariant = []): array
    {
        $variantsArr = $this->variantRows($p, $customPriceByProduct, $priceListByVariant);
        $customPrice = $customPriceByProduct[$p->id] ?? null;

        return [
            'id'                => $p->id,
            'name'              => $p->name,
            'slug'              => $p->slug,
            'sku'               => $p->sku,
            'image'             => optional($p->images->first())->url,
            'images'            => $p->images->map(fn ($img) => [
                'id'       => $img->id,
                'url'      => $img->url,
                'is_cover' => (bool) $img->is_cover,
            ])->values()->all(),
            'category_id'       => $p->category_id,
            'brand_id'          => $p->brand_id,
            'brand'             => $p->brand?->slug,
            'brandLabel'        => $p->brand?->name,
            'category'          => $p->category?->name,
            'categorySlug'      => $p->category?->slug,
            'gender'            => $p->gender,
            'price'             => $this->displayPrice($variantsArr, $customPrice, $p),
            'oldPrice'          => $p->old_price !== null ? (float) $p->old_price : null,
            'marketPrice'       => $p->market_price !== null ? (float) $p->market_price : null,
            'purchasePrice'     => $p->purchase_price !== null ? (float) $p->purchase_price : null,
            'barcode'           => $p->barcode,
            'desi'              => $p->desi !== null ? (float) $p->desi : null,
            'marketplaces'      => optional($p->category)->marketplaceMappings
                ?->map(fn ($m) => [
                    'key'      => $m->marketplace?->key,
                    'name'     => $m->marketplace?->name,
                    'color'    => $m->marketplace?->color,
                    'logoText' => $m->marketplace?->logo_text,
                ])->filter(fn ($x) => $x['key'])->values()->all() ?? [],
            'listings'          => $p->listings->mapWithKeys(fn ($l) => [
                $l->marketplace->key => [
                    'price'  => $l->price !== null ? (float) $l->price : null,
                    'isSent' => (bool) $l->is_sent,
                ],
            ])->all(),
            'stock'             => (int) ($p->variants_total_stock ?? 0),
            'rating'            => (float) $p->rating,
            'reviewCount'       => $p->review_count,
            'isNew'             => $p->is_new,
            'freeShipping'      => $p->free_shipping,
            'careInstructions'  => $p->care_instructions,
            'material'          => $p->material,
            'originCountry'     => $p->origin_country,
            'sizes'             => $this->sizes($p),
            'colors'            => $this->colors($p),
            'variants'          => $variantsArr,
            'readiness'         => $this->readinessByCapability($p),
        ];
    }

    /**
     * Kompakt ürün kartı (benzer ürünler, ProductCard bileşeni).
     *
     * @param  array<int,float>  $customPriceByProduct
     * @param  array<int,float>  $priceListByVariant
     * @return array<string, mixed>
     */
    public function card(Product $p, array $customPriceByProduct = [], array $priceListByVariant = []): array
    {
        $customPrice = $customPriceByProduct[$p->id] ?? null;
        $variantPrices = $p->variants->map(
            fn ($v) => $customPrice ?? ($priceListByVariant[$v->id] ?? null) ?? (float) $v->price
        )->all();
        $displayPrice = $variantPrices === []
            ? (float) ($customPrice ?? $p->price)
            : (float) min($variantPrices);

        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'slug'         => $p->slug,
            'sku'          => $p->sku,
            'image'        => $p->relationLoaded('images') ? optional($p->images->first())->url : null,
            'brand'        => $p->brand?->name,
            'brandSlug'    => $p->brand?->slug,
            'category'     => $p->category?->name,
            'categorySlug' => $p->category?->slug,
            'gender'       => $p->gender,
            'price'        => $displayPrice,
            'oldPrice'     => $p->old_price !== null ? (float) $p->old_price : null,
            'stock'        => (int) ($p->variants_total_stock ?? 0),
            'rating'       => (float) $p->rating,
            'reviewCount'  => $p->review_count,
            'isNew'        => (bool) $p->is_new,
            'freeShipping' => (bool) $p->free_shipping,
            'sizes'        => $this->sizes($p),
            'colors'       => $this->colors($p),
        ];
    }

    /**
     * Ürün detay sayfası payload'u. Açıklama, öne çıkan özellikler ve teknik
     * özellik tablosu tenant görünürlüğüne göre çözülür (ProductDisplayResolver).
     *
     * @param  array<int,float>  $customPriceByProduct
     * @param  array<int,float>  $priceListByVariant
     * @return array<string, mixed>
     */
    public function detail(Product $p, ?Tenant $tenant, array $customPriceByProduct = [], array $priceListByVariant = []): array
    {
        $variantsArr = $this->variantRows($p, $customPriceByProduct, $priceListByVariant);
        $customPrice = $customPriceByProduct[$p->id] ?? null;

        $composition = $this->composition($p);

        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'slug'         => $p->slug,
            'sku'          => $p->sku,
            'images'       => $p->images()
                ->reorder()->orderByDesc('is_cover')->orderBy('sort_order')
                ->pluck('path')->map(fn ($path) => Media::url($path))->filter()->values()->all(),
            'brand'        => $p->brand?->name ?? '',
            'brandSlug'    => $p->brand?->slug,
            'category'     => $p->category?->name ?? '',
            'categorySlug' => $p->category?->slug,
            'gender'       => $p->gender,
            'price'        => $this->displayPrice($variantsArr, $customPrice, $p),
            'oldPrice'     => $p->old_price !== null ? (float) $p->old_price : null,
            'stock'        => (int) ($p->variants_total_stock ?? 0),
            'rating'       => (float) $p->rating,
            'reviewCount'  => $p->review_count,
            'isNew'        => (bool) $p->is_new,
            'isBestSeller' => $p->review_count >= 200,
            'freeShipping' => (bool) $p->free_shipping,
            'sizes'        => $this->sizes($p),
            'colors'       => $this->colors($p),
            'variants'     => $variantsArr,
            'description'  => $this->display->for($p, $tenant)->description ?? '',
            'features'     => $this->features($p, $composition),
            'specs'        => $this->specs($p, $composition),
            'reviews'            => [],
            'ratingDistribution' => [],
        ];
    }

    /**
     * Varyant satırlarını tenant fiyatıyla çözer (katalog + detay ortak).
     *
     * @return array<int, array<string, mixed>>
     */
    private function variantRows(Product $p, array $customPriceByProduct, array $priceListByVariant): array
    {
        $customPrice = $customPriceByProduct[$p->id] ?? null;

        return $p->variants->map(function ($v) use ($customPrice, $priceListByVariant) {
            $resolved = $customPrice
                ?? ($priceListByVariant[$v->id] ?? null)
                ?? (float) $v->price;

            return [
                'id'         => $v->id,
                'size'       => $v->size,
                'color_name' => $v->color_name,
                'color_hex'  => $v->color_hex,
                'sku'        => $v->sku,
                'price'      => (float) $resolved,
                'old_price'  => $v->old_price !== null ? (float) $v->old_price : null,
                'stock'      => (int) $v->stock,
            ];
        })->values()->all();
    }

    /**
     * Ana gösterim fiyatı: en düşük varyant fiyatı (tenant'a göre çözülmüş).
     *
     * @param  array<int, array<string, mixed>>  $variantRows
     */
    private function displayPrice(array $variantRows, ?float $customPrice, Product $p): float
    {
        return $variantRows === []
            ? (float) ($customPrice ?? $p->price)
            : (float) min(array_column($variantRows, 'price'));
    }

    /**
     * Her capability için hazırlık durumu (Faz 4). `$p->images`/`variants`/
     * `category->marketplaceMappings` zaten yüklü — ekstra sorgu açmaz.
     *
     * @return array<string, array{ready: bool, score: int, missing: array<int, string>}>
     */
    private function readinessByCapability(Product $p): array
    {
        return collect(Capability::cases())
            ->mapWithKeys(fn (Capability $c) => [$c->value => $this->readiness->evaluate($p, $c)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function sizes(Product $p): array
    {
        return $p->variants->pluck('size')->filter()->unique()->values()->all();
    }

    /**
     * @return array<int, array{name: string, hex: string}>
     */
    private function colors(Product $p): array
    {
        return $p->variants
            ->filter(fn ($v) => $v->color_name && $v->color_hex)
            ->unique('color_name')
            ->map(fn ($v) => ['name' => $v->color_name, 'hex' => $v->color_hex])
            ->values()
            ->all();
    }

    /**
     * Ürünün kumaş/materyal bileşimini "Pamuk, Polyester" gibi okunur bir dizeye
     * çevirir. Materyal bağı yoksa null döner.
     */
    private function composition(Product $p): ?string
    {
        $names = $this->materialLinks->resolve($p)
            ->map(fn ($row) => $row['material']?->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : null;
    }

    /**
     * Detay sayfasındaki "öne çıkan özellikler" (yeşil tik) listesi. Yalnızca
     * gerçekten dolu/anlamlı alanlardan üretilir; uydurma değer yok.
     *
     * @return array<int, string>
     */
    private function features(Product $p, ?string $composition): array
    {
        $features = [];

        if ($p->free_shipping) {
            $features[] = 'Ücretsiz kargo';
        }
        if ($p->is_new) {
            $features[] = 'Yeni sezon ürünü';
        }
        if ($p->is_domestic) {
            $features[] = 'Yerli üretim';
        }
        if (filled($composition)) {
            $features[] = $composition . ' kumaş';
        } elseif (filled($p->material)) {
            $features[] = $p->material;
        }
        if ((int) $p->min_order_qty > 1) {
            $features[] = 'Minimum ' . (int) $p->min_order_qty . ' adet sipariş';
        }

        return $features;
    }

    /**
     * Detay sayfasındaki teknik özellik tablosu. Boş alanlar array_filter ile
     * elenir; iç kullanım alanları (alış fiyatı, GTIP vb.) dahil edilmez.
     *
     * @return array<string, string>
     */
    private function specs(Product $p, ?string $composition): array
    {
        $sizes  = $this->sizes($p);
        $colors = $this->colors($p);

        return array_filter([
            'Marka'        => $p->brand?->name,
            'Kategori'     => $p->category?->name,
            'Cinsiyet'     => $p->gender,
            'SKU'          => $p->sku,
            'Barkod'       => $p->barcode,
            'Materyal'     => $p->material,
            'Kumaş Bileşimi' => $composition,
            'Menşei'       => $p->origin_country,
            'Ağırlık'      => $p->weight !== null ? rtrim(rtrim(number_format((float) $p->weight, 3, ',', ''), '0'), ',') . ' kg' : null,
            'Desi'         => $p->desi !== null ? rtrim(rtrim(number_format((float) $p->desi, 2, ',', ''), '0'), ',') : null,
            'Bedenler'     => $sizes ? implode(', ', $sizes) : null,
            'Renkler'      => $colors ? collect($colors)->pluck('name')->implode(', ') : null,
            'Bakım'        => $p->care_instructions,
            'Kargo Süresi' => $p->shipping_time,
        ], fn ($v) => filled($v));
    }
}
