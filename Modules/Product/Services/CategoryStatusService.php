<?php

namespace Modules\Product\Services;

use Modules\Product\Models\Category;
use Modules\Product\Models\Product;

/**
 * Kategori durumunu ürünlere göre belirler:
 *  - elle pasife alınmış (manually_passive) → pasif kalır
 *  - kendisinde ya da alt kategorilerinden birinde yayında (published) ürün var → aktif
 *  - yoksa → pasif
 *
 * Ürün yayına alınınca / yayından kalkınca / kategori değiştirince / silinince ve kategori
 * kaydedilince/silinince tetiklenir (ProductServiceProvider). Günlük `categories:sync-status`
 * kaçan bir değişikliğe karşı güvenlik ağıdır. Durum değişimi model üzerinden kaydedilir ki
 * Bagisto senkronu (Category::updated) da çalışsın.
 */
class CategoryStatusService
{
    /** Senkron sırasında yapılan kategori kayıtları yeniden tetiklemesin. */
    private static bool $syncing = false;

    /**
     * Tüm kategorilerin durumunu yeniden hesaplar; değişen kategori sayısını döner.
     * Kategori tablosu küçük (yüzlerce satır) — her tetiklemede tamamını hesaplamak, etkilenen
     * dalları tek tek bulmaktan basit ve güvenli (taşınan alt ağaçlar, silinen üstler dahil).
     */
    public function syncAll(): int
    {
        if (self::$syncing) {
            return 0;
        }

        self::$syncing = true;
        try {
            // Bilinçli olarak tüm katalog: tenant bağlamı olmayan sistem işi, kategoriler paylaşımlı katalog
            // (tenant'a ait değil). Tam model okunur — Bagisto'nun updated hook'u slug vb. alanları kullanır.
            $categories = Category::query()->orderBy('id')->get();
            $published = Product::query()
                ->where('status', Product::STATUS_PUBLISHED)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->flip();

            $byId = $categories->keyBy('id');
            $hasProducts = [];
            foreach ($categories as $category) {
                if (! $published->has($category->id)) {
                    continue;
                }
                // Ürünü olan kategoriden köke kadar tüm üstler "ürünlü" (döngüye karşı $seen)
                $seen = [];
                for ($node = $category; $node && ! isset($seen[$node->id]); $node = $byId->get($node->parent_id)) {
                    $seen[$node->id] = true;
                    $hasProducts[$node->id] = true;
                }
            }

            $changed = 0;
            foreach ($categories as $category) {
                $status = ! $category->manually_passive && isset($hasProducts[$category->id]) ? 'active' : 'passive';
                if ($category->status !== $status) {
                    $category->update(['status' => $status]);
                    $changed++;
                }
            }

            return $changed;
        } finally {
            self::$syncing = false;
        }
    }

    /** Ürünün kategori durumunu etkileyebilecek bir değişikliği var mı? */
    public function productAffectsCategories(Product $product): bool
    {
        return $product->wasRecentlyCreated
            || $product->wasChanged(['status', 'category_id', 'deleted_at']);
    }
}
