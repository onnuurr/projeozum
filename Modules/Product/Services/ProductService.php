<?php

namespace Modules\Product\Services;

use App\Support\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Product\Exceptions\BarcodeGenerationException;
use Modules\Product\Models\Product;
use Modules\Superadmin\Models\Setting;

/**
 * Ürün yazma orkestrasyonu (create/update/delete).
 *
 * Controller yalnızca doğrulanmış veriyi geçirir; varyant senkronu, materyal
 * bağı, görsel yükleme ve fiyat türetme burada tek transaction içinde toplanır.
 * Böylece ProductController ince kalır (bkz. Services/OrderService deseni).
 */
class ProductService
{
    public function __construct(private ProductMaterialLinkService $materialLinks) {}

    /**
     * Yeni ürün oluşturur; varyant + materyal bağını kurar, görselleri yükler.
     *
     * @param  array<string, mixed>       $data    Doğrulanmış form verisi.
     * @param  array<int, UploadedFile>   $images  Yüklenen görsel dosyaları (opsiyonel).
     */
    public function create(array $data, array $images = []): Product
    {
        $product = DB::transaction(function () use ($data) {
            $attributes = $this->attributesFrom($data);

            if (empty($attributes['barcode'])) {
                try {
                    $attributes['barcode'] = $this->generateBarcode();
                } catch (BarcodeGenerationException) {
                    // Barkod ayarları henüz yapılandırılmamış/hatalı — ürün kaydı engellenmez,
                    // barkod boş kalır (bkz. plan Karar 5).
                }
            }

            $product = Product::create($attributes);

            $this->syncVariants($product, $data['variants']);
            $this->materialLinks->syncMaterials($product, $data['description_materials'] ?? []);

            return $product;
        });

        $this->storeImages($product, $images);

        return $product;
    }

    /**
     * Mevcut ürünü günceller; varyant + materyal bağını yeniden kurar, yeni görselleri ekler.
     *
     * @param  array<string, mixed>       $data
     * @param  array<int, UploadedFile>   $images
     */
    public function update(Product $product, array $data, array $images = []): Product
    {
        DB::transaction(function () use ($product, $data) {
            $product->update($this->attributesFrom($data));

            $this->syncVariants($product, $data['variants']);
            $this->materialLinks->syncMaterials($product, $data['description_materials'] ?? []);
        });

        $this->storeImages($product, $images);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * Birden çok ürünü topluca siler (model olayları tetiklensin diye döngüyle).
     *
     * @param  array<int, int>  $ids
     * @return int  Silinen ürün sayısı.
     */
    public function bulkDelete(array $ids): int
    {
        $count = 0;

        DB::transaction(function () use ($ids, &$count) {
            foreach (Product::whereIn('id', $ids)->get() as $product) {
                $product->delete();
                $count++;
            }
        });

        return $count;
    }

    /**
     * Superadmin ayarlarındaki (ülke kodu + firma kodu) sabit önek ve seri aralığına göre
     * benzersiz bir EAN-13/GS1 barkod üretir. Aralıkta rastgele seri no dener, DB'de çakışırsa
     * 20 kere tekrar dener; bulamazsa rangeExhausted fırlatır. Önek/aralık 12 haneye sığmıyorsa
     * misconfigured fırlatır.
     */
    public function generateBarcode(): string
    {
        $settings    = Setting::getGroup('barcode');
        $countryCode = trim((string) ($settings['countryCode'] ?? ''));
        $companyCode = trim((string) ($settings['companyCode'] ?? ''));
        $serialMin   = (int) ($settings['serialMin'] ?? 0);
        $serialMax   = (int) ($settings['serialMax'] ?? 0);

        $prefix     = $countryCode . $companyCode;
        $prefixLen  = strlen($prefix);
        $slotWidth  = 12 - $prefixLen;

        if ($countryCode === '' || $companyCode === '' || $prefixLen > 11 || ! ctype_digit($prefix)) {
            throw BarcodeGenerationException::misconfigured('ülke/firma kodu tanımlı değil veya sayısal değil.');
        }

        if ($serialMax < $serialMin || $slotWidth < strlen((string) $serialMax)) {
            throw BarcodeGenerationException::misconfigured('seri aralığı önekle birlikte 12 haneye sığmıyor.');
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $serial    = random_int($serialMin, $serialMax);
            $twelve    = $prefix . str_pad((string) $serial, $slotWidth, '0', STR_PAD_LEFT);
            $candidate = $twelve . $this->eanCheckDigit($twelve);

            if (! Product::where('barcode', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw BarcodeGenerationException::rangeExhausted($serialMin, $serialMax);
    }

    /**
     * EAN-13 mod-10 kontrol hanesini hesaplar (sağdan sola alternatif 3x/1x ağırlık).
     */
    private function eanCheckDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split(strrev($twelveDigits)) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Varyantları tam senkronlar (sil-ve-yeniden-oluştur). Fiyat/eski fiyat ürün
     * seviyesinde en düşük varyanttan türetilir (bkz. attributesFrom).
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $product->variants()->delete();

        foreach ($variants as $idx => $v) {
            $product->variants()->create([
                'size'       => $v['size'] ?? null,
                'color_name' => $v['color_name'] ?? null,
                'color_hex'  => $v['color_hex'] ?? null,
                'sku'        => $v['sku'],
                'price'      => $v['price'],
                'old_price'  => $v['old_price'] ?? null,
                'stock'      => $v['stock'] ?? 0,
                'sort_order' => $idx,
            ]);
        }
    }

    /**
     * Yüklenen görselleri ürüne kaydeder. Üründe kapak yoksa ilk yüklenen kapak olur.
     *
     * @param  array<int, UploadedFile>  $images
     */
    private function storeImages(Product $product, array $images): void
    {
        if ($images === []) {
            return;
        }

        $hasCover = $product->images()->where('is_cover', true)->exists();
        $sort     = (int) $product->images()->max('sort_order');

        foreach ($images as $file) {
            $path = $file->store('products/' . $product->id, Media::disk());

            $product->images()->create([
                'path'       => $path,
                'sort_order' => ++$sort,
                'is_cover'   => ! $hasCover,
            ]);

            $hasCover = true;
        }
    }

    /**
     * Doğrulanmış form verisinden products tablosu attribute dizisini üretir.
     * Fiyat varyantlardan türetilir; slug boşsa model otomatik üretir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFrom(array $data): array
    {
        return [
            'category_id'       => $data['category_id'],
            'brand_id'          => $data['brand_id'] ?? null,
            'name'              => $data['name'],
            'slug'              => $data['slug'] ?? null,
            'sku'               => $data['sku'],
            'gender'            => $data['gender'],
            'price'             => $this->minPrice($data['variants']),
            'old_price'         => $this->minOldPrice($data['variants']),
            'market_price'      => $data['market_price'] ?? null,
            'purchase_price'    => $data['purchase_price'] ?? null,
            'is_new'            => $data['is_new'] ?? false,
            'free_shipping'     => $data['free_shipping'] ?? false,
            'care_instructions' => $data['care_instructions'] ?? null,
            'material'          => $data['material'] ?? null,
            'origin_country'    => $data['origin_country'] ?? 'TR',
            'public_name'        => $data['public_name'] ?? null,
            'public_description' => $data['public_description'] ?? null,
            'tenant_description' => $data['tenant_description'] ?? null,
            // SEO
            'meta_title'        => $data['meta_title'] ?? null,
            'meta_description'  => $data['meta_description'] ?? null,
            'meta_keywords'     => $data['meta_keywords'] ?? null,
            // Kargo
            'weight'            => $data['weight'] ?? null,
            'desi'              => $data['desi'] ?? null,
            'shipping_time'     => $data['shipping_time'] ?? null,
            'shipping_fee'      => $data['shipping_fee'] ?? null,
            // Diğer
            'barcode'           => $data['barcode'] ?? null,
            'is_domestic'       => $data['is_domestic'] ?? false,
            'manufacturer_code' => $data['manufacturer_code'] ?? null,
            'gtip_code'         => $data['gtip_code'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function minPrice(array $variants): float
    {
        $prices = array_column($variants, 'price');

        return $prices === [] ? 0.0 : (float) min($prices);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function minOldPrice(array $variants): ?float
    {
        $olds = array_filter(array_column($variants, 'old_price'), fn ($v) => $v !== null && $v !== '');

        return $olds === [] ? null : (float) min($olds);
    }
}
