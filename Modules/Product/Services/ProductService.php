<?php

namespace Modules\Product\Services;

use App\Models\User;
use App\Support\Media;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Product\Events\MaterialChanged;
use Modules\Product\Events\MediaUploaded;
use Modules\Product\Events\ProductArchived;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductPublished;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Events\ProductVariantsSynced;
use Modules\Product\Exceptions\BarcodeGenerationException;
use Modules\Product\Exceptions\InvalidProductTransitionException;
use Modules\Product\Exceptions\VariantInUseException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductStatusHistory;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
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
    public function __construct(
        private ProductMaterialLinkService $materialLinks,
        private ProductAttributeService $productAttributes,
    ) {}

    /**
     * Yeni ürün oluşturur; varyant + materyal bağını kurar, görselleri yükler.
     *
     * @param  array<string, mixed>       $data    Doğrulanmış form verisi.
     * @param  array<int, UploadedFile>   $images  Yüklenen görsel dosyaları (opsiyonel).
     * @param  string                     $status  Başlangıç durumu; Atölye'nin "Üretilecek Ürün"
     *                                             akışı STATUS_DEVELOPMENT (geçici kart) geçirir.
     * @param  string                     $sourcing  SOURCING_PURCHASED | SOURCING_MANUFACTURED.
     */
    public function create(
        array $data,
        array $images = [],
        string $status = Product::STATUS_PUBLISHED,
        string $sourcing = Product::SOURCING_PURCHASED,
    ): Product {
        $product = DB::transaction(function () use ($data, $status, $sourcing) {
            $attributes = $this->attributesFrom($data) + ['status' => $status, 'sourcing' => $sourcing];

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

        $this->dispatchWriteEvents(new ProductCreated($product), $product, $data, $images);

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

        $this->dispatchWriteEvents(new ProductUpdated($product), $product, $data, $images);

        return $product;
    }

    /**
     * create()/update() ortak event dispatch'i. Transaction commit edip
     * `storeImages()` de dahil tüm yan etkiler bittikten SONRA çağrılır — rollback
     * olursa hiçbir event ateşlenmemiş olur, Timeline'da hayalet kayıt oluşmaz.
     *
     * @param  array<string, mixed>      $data
     * @param  array<int, UploadedFile>  $images
     */
    private function dispatchWriteEvents(ProductCreated|ProductUpdated $primary, Product $product, array $data, array $images): void
    {
        event($primary);

        ProductVariantsSynced::dispatch($product, count($data['variants']));

        if (! empty($data['description_materials'])) {
            MaterialChanged::dispatch($product);
        }

        if ($images !== []) {
            MediaUploaded::dispatch($product, count($images));
        }
    }

    /**
     * Ürünü yeni bir yaşam döngüsü durumuna geçirir (Faz 2). `OrderService::transition()`
     * ile aynı iskelet: harita kontrolü + durum güncellemesi + denetim satırı tek
     * transaction içinde; event dispatch transaction commit'inden SONRA yapılır
     * (rollback olursa event hiç ateşlenmez, bkz. Faz 1 ilkesi).
     *
     * @throws InvalidProductTransitionException  geçiş haritada tanımlı değilse.
     */
    public function transitionStatus(Product $product, string $to, User $actor, ?string $note = null): Product
    {
        $allowed = Product::allowedTransitions()[$product->status] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new InvalidProductTransitionException($product->status, $to);
        }

        return $this->applyStatus($product, $to, $actor, $note);
    }

    /**
     * Geçici kartı (status=development) ürüne çevirir: verilen birim maliyeti alış fiyatı
     * olarak yazar ve ürünü Taslak'a alır. Elle geçiş haritasında development → draft yoktur;
     * bu geçişi yalnız iş emrinin ilk stok girişi (Atölye ProductionOrderService::receive)
     * tetikler. Ürün zaten geçici kart değilse no-op.
     */
    public function promoteFromDevelopment(Product $product, float $unitCost, ?User $actor, ?string $note = null): Product
    {
        if ($product->status !== Product::STATUS_DEVELOPMENT) {
            return $product;
        }

        return $this->applyStatus($product, Product::STATUS_DRAFT, $actor, $note, [
            'purchase_price' => round($unitCost, 2),
        ]);
    }

    /**
     * Üretim kapanınca gerçekleşen birim maliyeti alış fiyatı olarak yazar (Atölye
     * ProductionOrderService::close). Satış tarafına yansımayan iç maliyet alanı olduğu
     * için ürün event'i ateşlenmez.
     */
    public function updatePurchasePrice(Product $product, float $unitCost): Product
    {
        $product->forceFill(['purchase_price' => round($unitCost, 2)])->save();

        return $product;
    }

    /**
     * Durum güncellemesi + denetim satırı tek transaction içinde; event dispatch
     * transaction commit'inden SONRA (rollback olursa event hiç ateşlenmez, bkz. Faz 1 ilkesi).
     *
     * @param  array<string, mixed>  $extra  Durumla birlikte yazılacak ek kolonlar.
     */
    private function applyStatus(Product $product, string $to, ?User $actor, ?string $note, array $extra = []): Product
    {
        $from = $product->status;

        $product = DB::transaction(function () use ($product, $from, $to, $actor, $note, $extra) {
            $product->forceFill(['status' => $to] + $extra)->save();

            ProductStatusHistory::create([
                'product_id'  => $product->id,
                'from_status' => $from,
                'to_status'   => $to,
                'user_id'     => $actor?->id,
                'note'        => $note,
            ]);

            return $product;
        });

        match ($to) {
            Product::STATUS_PUBLISHED => ProductPublished::dispatch($product),
            Product::STATUS_ARCHIVED  => ProductArchived::dispatch($product),
            default                   => null,
        };

        ProductStatusChanged::dispatch($product, $from, $to);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();

        ProductDeleted::dispatch($product);
    }

    /**
     * Birden çok ürünü topluca siler (model olayları tetiklensin diye döngüyle).
     * Event dispatch, transaction commit'inden SONRA yapılır (bkz. dispatchWriteEvents).
     *
     * @param  array<int, int>  $ids
     * @return int  Silinen ürün sayısı.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = DB::transaction(function () use ($ids) {
            $products = Product::whereIn('id', $ids)->get();

            foreach ($products as $product) {
                $product->delete();
            }

            return $products;
        });

        foreach ($deleted as $product) {
            ProductDeleted::dispatch($product);
        }

        return $deleted->count();
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
            throw BarcodeGenerationException::misconfigured(__('product::messages.barcode_reasons.prefix_invalid'));
        }

        if ($serialMax < $serialMin || $slotWidth < strlen((string) $serialMax)) {
            throw BarcodeGenerationException::misconfigured(__('product::messages.barcode_reasons.range_too_long'));
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
     * Varyantları SKU'ya göre senkronlar: mevcut SKU yerinde güncellenir (kimlik ve ona bağlı
     * depo stokları, stok hareketleri, fiyat listeleri korunur), yeni SKU eklenir, formdan
     * çıkarılan SKU silinir. Fiyat/eski fiyat ürün seviyesinde en düşük varyanttan türetilir
     * (bkz. attributesFrom).
     *
     * (Eskiden sil-ve-yeniden-oluştur idi: her düzenleme stocks/stock_movements/price_lists'i
     * CASCADE ile siliyor, üretim emrindeki varyant için RESTRICT'e çarpıp kaydı düşürüyordu.)
     *
     * Mevcut varyantın `stock` alanı formdan GÜNCELLENMEZ — stok yalnız StockService hareketiyle
     * değişir (depo dağılımıyla tutarlı kalsın diye); form değeri yalnız yeni varyantın başlangıcı.
     *
     * @param  array<int, array<string, mixed>>  $variants
     *
     * @throws VariantInUseException  çıkarılan varyantın stoku/geçmişi var ya da başka kayıtta kullanılıyor.
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->get()->keyBy('sku');
        $keep     = [];

        foreach ($variants as $idx => $v) {
            $attributes = [
                'size'       => $v['size'] ?? null,
                'color_name' => $v['color_name'] ?? null,
                'color_hex'  => $v['color_hex'] ?? null,
                'price'      => $v['price'],
                'old_price'  => $v['old_price'] ?? null,
                'sort_order' => $idx,
            ];

            if ($variant = $existing->get($v['sku'])) {
                $variant->update($attributes);
            } else {
                $product->variants()->create($attributes + ['sku' => $v['sku'], 'stock' => $v['stock'] ?? 0]);
            }

            $keep[] = $v['sku'];
        }

        // Eloquent\Collection::except() modelin birincil anahtarına göre çalışır, keyBy
        // anahtarına değil — çıkarılanları SKU ile açıkça süz.
        foreach ($existing->reject(fn (ProductVariant $v) => in_array($v->sku, $keep, true)) as $variant) {
            $this->deleteRemovedVariant($variant);
        }
    }

    /**
     * Formdan çıkarılan varyantı siler. Stoku veya hareket geçmişi olan varyant silinmez (CASCADE
     * bunları sessizce yok ederdi). Başka modüllerin referansları (ör. Atölye üretim emri, RESTRICT)
     * savepoint içinde denenerek yakalanır — Product o tabloları bilmek zorunda kalmaz.
     */
    private function deleteRemovedVariant(ProductVariant $variant): void
    {
        $hasStock = Stock::query()
            ->where('product_variant_id', $variant->id)
            ->where(fn ($q) => $q->where('quantity', '!=', 0)->orWhere('reserved_quantity', '!=', 0))
            ->exists();

        if ($hasStock || (int) $variant->stock !== 0) {
            throw new VariantInUseException($variant->sku, __('product::messages.variant_in_use_reasons.has_stock'));
        }

        if (StockMovement::query()->where('product_variant_id', $variant->id)->exists()) {
            throw new VariantInUseException($variant->sku, __('product::messages.variant_in_use_reasons.has_movements'));
        }

        try {
            DB::transaction(fn () => $variant->delete());
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new VariantInUseException($variant->sku, __('product::messages.variant_in_use_reasons.referenced'));
            }

            throw $e;
        }
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        // Postgres: 23503; SQLite (testler): 23000 + "FOREIGN KEY constraint failed".
        return $e->getCode() === '23503'
            || ($e->getCode() === '23000' && str_contains($e->getMessage(), 'FOREIGN KEY'));
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

        // Çağıran create()/update() hemen ardından ProductCreated/ProductUpdated fırlatır —
        // görsel başına ayrı ProductImagesChanged duyurusu gereksiz (ve sırayı bozar).
        ProductImage::withoutChangeAnnouncements(function () use ($product, $images, &$hasCover, &$sort) {
            foreach ($images as $file) {
                $path = $file->store('products/' . $product->id, Media::disk());

                $product->images()->create([
                    'path'       => $path,
                    'sort_order' => ++$sort,
                    'is_cover'   => ! $hasCover,
                ]);

                $hasCover = true;
            }
        });
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
            'attributes'        => $this->productAttributes->filterToDefinedKeys(
                $data['category_id'] ?? null,
                $data['attributes'] ?? [],
            ),
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
