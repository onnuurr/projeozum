<?php

namespace Modules\Bagisto\Mappers;

use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAttributeService;
use Modules\Product\Services\VatRateService;

/**
 * `Product` + varyantlarını, Bagisto tarafındaki
 * `Webkul\SaasSync\Http\Controllers\ProductWebhookController::handle()`
 * validasyonunun beklediği payload şekline çevirir.
 *
 * Her varyant her zaman gönderilir (renk/beden boş olsa bile) — SaaS'ta bir
 * ürün asla varyantsız olmadığı için (bkz. `ProductService::create()`),
 * Bagisto tarafında da her zaman configurable akış kullanılır.
 */
class ProductPayloadMapper
{
    public function __construct(
        private readonly ProductAttributeService $productAttributes,
        private readonly VatRateService $vat,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toUpsertPayload(Product $product, string $event): array
    {
        return [
            'event'                 => $event,
            'sku'                   => $product->sku,
            'attribute_family_code' => config('bagisto.sync.attribute_family_code'),
            'name'                  => $product->name,
            'weight'                => $product->weight !== null ? (float) $product->weight : null,
            'short_description'     => $product->public_description,
            'description'           => $product->tenant_description ?? $product->public_description,
            'status'                => $product->status === Product::STATUS_PUBLISHED,
            // Boş string = Bagisto'da değeri temizle (null gönderilirse Bagisto alanı atlar, eski değer kalırdı).
            'brand'                 => $product->brand?->name ?? '',
            'new'                   => (bool) $product->is_new,
            'featured'              => (bool) $product->is_featured,
            // Üretim bilgisi → Bagisto'da SaaS'tan yönetilen kumas_icerigi (menşei, yıkama talimatı: Özellikler)
            'material'              => $product->material ?? '',
            // Sipariş başına en fazla adet → Bagisto maks_satis_adedi (sepette uygulanır); null = temizle
            'max_order_qty'         => $product->max_order_qty,
            'meta_title'            => $product->meta_title ?? '',
            'meta_description'      => $product->meta_description ?? '',
            'meta_keywords'         => $product->meta_keywords ?? '',
            'product_number'        => $product->barcode ?? '',
            // Kargo paketi ölçüleri (cm) → Bagisto'nun hazır length/width/height alanları; boş = temizle
            'length'                => $this->dimension($product->length_cm),
            'width'                 => $this->dimension($product->width_cm),
            'height'                => $this->dimension($product->height_cm),
            'attributes'            => $this->attributeValues($product),
            // Bagisto "fiyatlar KDV dahil" modunda KDV'yi bu vergi kategorisiyle ayrıştırır; %0 = kategori yok
            'tax_category_code'     => ($rate = $this->vat->rateFor($product)) > 0 ? 'kdv-'.rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') : '',
            'category_codes'        => $product->category ? [$product->category->slug] : [],
            'category_name'         => $product->category?->name,
            // Genel galeri: genel görseller + varsayılan beden seti (galeri sırasındaki ilk bedene özel görselin
            // seti — push kapağı başa aldığı için kapağın seti), SaaS'taki sırayla. Diğer beden setleri yalnız
            // kendi bedenlerinin varyantlarına gider (mağazada beden seçilince galerinin başına gelir).
            'images'                => $product->images->filter(fn ($img) => $img->variant_sizes === null || $this->isDefaultSet($product, $img))
                ->pluck('url')->values()->all(),
            'variants'              => $product->variants->map(fn ($variant) => [
                'sku'      => $variant->sku,
                ...$this->variantPrices($variant, $this->vat->rateFor($product)),
                'quantity' => (int) $variant->stock,
                'color'    => $variant->color_name,
                'size'     => $variant->size,
                'images'   => $this->variantImages($product, $variant),
            ])->all(),
        ];
    }

    /**
     * Varyantın görselleri: doğrudan bağlı olanlar + bedeni variant_sizes'ta geçen bedene özel görseller.
     * Renk: varyantın rengine özel set (variant_colors) varsa o, yoksa genel set (variant_colors null).
     * Bedene özel görseli olan üründe liste boşsa [] gider (Bagisto varyantın eski setini temizler);
     * hiç bedene özel görseli olmayan üründe null (Bagisto mevcut görsellere dokunmaz — eski davranış).
     *
     * @param  \Modules\Product\Models\ProductVariant  $variant
     * @return list<string>|null
     */
    private function variantImages(Product $product, $variant): ?array
    {
        $scoped = $product->images->filter(fn ($img) => $img->variant_sizes !== null);
        $forSize = $scoped->filter(fn ($img) => in_array($variant->size, (array) $img->variant_sizes, true));
        $exact = $forSize->filter(fn ($img) => in_array($variant->color_name, (array) $img->variant_colors, true));
        $sets = $exact->isNotEmpty() ? $exact : $forSize->filter(fn ($img) => $img->variant_colors === null);
        // Varsayılan set zaten genel galerinin başında: varyanta ayrıca gitmez (tema varyant görsellerini
        // galerinin önüne ekliyor — aynı mokaplar iki kez görünürdü).
        $sets = $sets->reject(fn ($img) => $this->isDefaultSet($product, $img));
        $urls = $product->images->filter(fn ($img) => $img->product_variant_id === $variant->id)
            ->merge($sets)->sortBy('sort_order')->pluck('url')->unique()->values()->all();

        return $urls !== [] ? $urls : ($scoped->isNotEmpty() ? [] : null);
    }

    /**
     * Görsel varsayılan beden setinden mi: galeri sırasındaki ilk (bedenleri dolu) bedene özel görselle aynı
     * beden + renk kümesi. Bedene özel görseli olmayan üründe hiçbiri.
     */
    private function isDefaultSet(Product $product, $img): bool
    {
        $anchor = $product->images->first(fn ($i) => ! empty($i->variant_sizes));
        $norm = fn ($v) => $v === null ? null : collect($v)->sort()->values()->all();

        return $anchor !== null && ! empty($img->variant_sizes)
            && $norm($img->variant_sizes) === $norm($anchor->variant_sizes)
            && $norm($img->variant_colors) === $norm($anchor->variant_colors);
    }

    /** Bagisto'da metin alanı: "12.5" gibi sade sayı, yoksa boş string. */
    private function dimension(mixed $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * Ürün Ayarları → Özellikler değerleri koda göre, okunur hâlde (seçenek adı; Bagisto seçeneği
     * adla çözer). Sistem özellikleri (KDV) hariç tüm özellik kodları gider: üründe değeri olmayan (ya da kategorisinde artık
     * geçerli olmayan) özellik boş gönderilir ki Bagisto'daki eski değer temizlensin.
     *
     * @return array<string, string|bool|list<string>>
     */
    private function attributeValues(Product $product): array
    {
        $values = $this->productAttributes->labelledValues($product);

        return ProductAttribute::query()->whereNotIn('code', ProductAttribute::SYSTEM_CODES)->get(['code', 'type'])
            ->mapWithKeys(fn (ProductAttribute $a) => [
                $a->code => $values[$a->code] ?? match ($a->type) {
                    ProductAttribute::TYPE_MULTISELECT, ProductAttribute::TYPE_CHECKBOX => [],
                    ProductAttribute::TYPE_BOOLEAN => false,
                    default => '',
                },
            ])
            ->all();
    }

    /**
     * SaaS'ta `price` güncel satış fiyatı, `old_price` üstü çizili eski fiyattır; Bagisto'da tersi:
     * `price` normal fiyat, `special_price` indirimli fiyat. Eski fiyat güncelden büyükse indirim
     * vardır; yoksa special_price boş gider (Bagisto'daki eski indirim temizlenir).
     *
     * SaaS fiyatları KDV hariçtir; mağazaya ürünün KDV oranıyla (ürün → kategori) KDV dahil gider (VatRateService).
     * Bayi iskontosu Bagisto'da bu KDV dahil fiyata yüzde olarak uygulanır — sonuç, SaaS siparişindeki
     * (iskontolu net + KDV) tutarla aynıdır.
     *
     * @return array{price: float, special_price: float|string}
     */
    private function variantPrices(ProductVariant $variant, float $vatRate): array
    {
        $price = $this->vat->gross((float) $variant->price, $vatRate);
        $oldPrice = $this->vat->gross((float) $variant->old_price, $vatRate);

        return $oldPrice > $price
            ? ['price' => $oldPrice, 'special_price' => $price]
            : ['price' => $price, 'special_price' => ''];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDeletePayload(Product $product): array
    {
        return [
            'event' => 'deleted',
            'sku'   => $product->sku,
        ];
    }
}
