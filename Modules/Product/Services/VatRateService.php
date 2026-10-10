<?php

namespace Modules\Product\Services;

use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductAttributeOption;

/**
 * KDV oranı çözümü. SaaS fiyatları KDV hariç tutulur. Oran sırası: üründe seçili `kdv_orani` sistem
 * özelliği (Ürün Ayarları → Özellikler; seçenek adı oran, ör. "%10") → kategori (boşsa üst kategoriden
 * miras) → config('product.default_vat_rate'). Mağazaya (Bagisto) giden fiyat ve
 * bayi siparişindeki KDV bu oranla hesaplanır.
 */
class VatRateService
{
    /** Bagisto'da vergi kategorisi (kdv-N) olan oranlar; kategori formu ve KDV seçenekleri bunlarla sınırlı. */
    public const ALLOWED_RATES = [0.0, 1.0, 10.0, 20.0];

    /** @var array<int, array{parent_id: int|null, vat_rate: float|null}>|null */
    private ?array $categories = null;

    /** @var array<int, float>|null KDV özelliğinin seçenek id → oran */
    private ?array $optionRates = null;

    public function defaultRate(): float
    {
        return (float) config('product.default_vat_rate', 10);
    }

    public function rateForCategory(?int $categoryId): float
    {
        $categories = $this->categories ??= Category::withTrashed()
            ->get(['id', 'parent_id', 'vat_rate'])
            ->mapWithKeys(fn (Category $c) => [$c->id => [
                'parent_id' => $c->parent_id,
                'vat_rate'  => $c->vat_rate !== null ? (float) $c->vat_rate : null,
            ]])
            ->all();

        $seen = [];
        for ($id = $categoryId; $id !== null && isset($categories[$id]) && ! isset($seen[$id]); $id = $categories[$id]['parent_id']) {
            $seen[$id] = true;
            if ($categories[$id]['vat_rate'] !== null) {
                return $categories[$id]['vat_rate'];
            }
        }

        return $this->defaultRate();
    }

    public function rateFor(?Product $product): float
    {
        $optionId = ($product?->getAttribute('attributes') ?? [])[ProductAttribute::VAT_CODE] ?? null;

        return $this->optionRates()[(int) $optionId] ?? $this->rateForCategory($product?->category_id);
    }

    /**
     * KDV özelliğinin seçenekleri (ürün formu): [{value: seçenek id, label: ad, rate}].
     *
     * @return list<array{value: int, label: string, rate: float}>
     */
    public function options(): array
    {
        return ProductAttributeOption::query()
            ->whereHas('attribute', fn ($q) => $q->where('code', ProductAttribute::VAT_CODE))
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->filter(fn (ProductAttributeOption $o) => self::parseRate($o->name) !== null)
            ->map(fn (ProductAttributeOption $o) => ['value' => $o->id, 'label' => $o->name, 'rate' => self::parseRate($o->name)])
            ->values()
            ->all();
    }

    /** "%10", "10", "%1,5" → oran; sayı değilse ya da 0–100 dışındaysa null. */
    public static function parseRate(?string $name): ?float
    {
        $text = str_replace([',', '%', ' '], ['.', '', ''], (string) $name);

        return is_numeric($text) && (float) $text >= 0 && (float) $text <= 100 ? (float) $text : null;
    }

    /** @return array<int, float> */
    private function optionRates(): array
    {
        return $this->optionRates ??= collect($this->options())->pluck('rate', 'value')->all();
    }

    /** KDV hariç tutara KDV ekler (kuruşa yuvarlanır). */
    public function gross(float $net, float $rate): float
    {
        return round($net * (1 + $rate / 100), 2);
    }

    public function vatOf(float $net, float $rate): float
    {
        return round($net * $rate / 100, 2);
    }

    /** Kategori kaydedilince aynı istek/worker içinde bayat kalmasın. */
    public function flush(): void
    {
        $this->categories = null;
        $this->optionRates = null;
    }
}
