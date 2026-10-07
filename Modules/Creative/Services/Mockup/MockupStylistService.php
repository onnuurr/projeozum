<?php

namespace Modules\Creative\Services\Mockup;

use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\StylistOption;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder;
use Modules\Product\Models\Product;

/**
 * Ürüne özel kombini ve pozları kurar (Faz 3). Stilist (AI) yalnız seçenek listesinden ANAHTAR seçer;
 * MockupStylistRuleEngine anahtarları doğrular, uyumsuzları eler, çeşitlilik kuralını uygular ve kombin
 * cümlesini seçeneklerin prompt_en alanlarından kurar. Boy yalnız ürün özelliği yoksa AI'dan alınır.
 */
class MockupStylistService
{
    public function __construct(
        private MockupGeneratorContract $generator,
        private MockupStylistRuleEngine $rules,
    ) {}

    /**
     * Mokapta bu bölge için kullanılabilir pozlar (ana kare hariç), sıralı:
     * pose_key => {prompt, regions, requires}.
     *
     * @return array<string,array{prompt:string,regions:array<int,string>,requires:array<int,string>}>
     */
    public function allowedPoses(string $region): array
    {
        return Pose::query()->where('status', Pose::STATUS_READY)
            ->where('pose_key', '!=', (string) config('creative.mockup.master_pose'))
            ->whereNotNull('preview_image_path')->orderBy('sort_order')->get()
            ->filter(fn (Pose $p) => in_array($region, (array) $p->mockup_regions, true))
            ->mapWithKeys(fn (Pose $p) => [$p->pose_key => ['prompt' => (string) $p->prompt, 'regions' => (array) $p->mockup_regions, 'requires' => (array) $p->requires]])
            ->all();
    }

    /** @param  list<string>  $extraPhotoPaths  Aynı ürünün diğer fotoğrafları (yerel yol; ör. takımın ayrı çekilmiş altı) */
    public function style(MockupOutfit $outfit, string $productPhotoPath, array $extraPhotoPaths = []): MockupOutfit
    {
        $product = $outfit->product;
        $poses = $this->allowedPoses($outfit->region);
        $extra = min((int) config('creative.mockup.extra_poses', 2), count($poses));
        $gender = $this->genderWord($product);
        $catalog = StylistOption::catalog();

        // Stiliste yalnız bu ürüne uyan seçenekler gösterilir (kural motoru yine de doğrular).
        $probe = ['region' => $outfit->region, 'length' => $outfit->length ?: '', 'gender' => $gender, 'extra_poses' => $extra, 'recent' => []];
        $shown = [];
        foreach ($this->kindsFor($outfit->region, $outfit->length) as $kind) {
            foreach ($catalog[$kind] ?? [] as $key => $opt) {
                if ($this->rules->allows($kind, (string) $key, $catalog, $probe)) {
                    $shown[$kind][$key] = $opt['prompt_en'] !== '' ? $opt['prompt_en'] : 'none';
                }
            }
        }

        $raw = $this->generator->stylist($productPhotoPath, [
            'region'      => $outfit->region,
            'length'      => $outfit->length ?: 'unknown',
            'age'         => $this->attributeName($product, 'yas_grubu'),
            'gender'      => $gender,
            'poses'       => array_map(fn ($p) => $p['prompt'], $poses),
            'extra_poses' => $extra,
            'options'     => $shown,
        ], $extraPhotoPaths);

        $length = $outfit->length;
        $lengthGuessed = false;
        if (! $length) {
            $lengthGuessed = true;
            $length = in_array($raw['length'] ?? null, MockupOutfit::LENGTHS, true) ? $raw['length'] : 'knee';
        }

        $resolved = $this->rules->resolve($raw, $catalog, $poses, [
            'region'      => $outfit->region,
            'length'      => $length,
            'gender'      => $gender,
            'extra_poses' => $extra,
            'recent'      => $this->recentCombos($outfit),
        ]);
        $warnings = $resolved['warnings'];
        if ($lengthGuessed) {
            $warnings[] = 'Ürünün boyu özellikten okunamadı; stilist tahmin etti (' . $length . ').';
        }

        $outfit->update([
            'length'                 => $length,
            'pieces'                 => $resolved['pieces'],
            'outfit_sentence'        => $resolved['sentence'],
            'poses'                  => $resolved['poses'],
            'reasoning_tr'           => trim((string) ($raw['reasoning_tr'] ?? '')) ?: null,
            'stylist_prompt_version' => MockupPromptBuilder::STYLIST_VERSION,
            'meta'                   => [...($outfit->meta ?? []),
                'product_summary'    => trim((string) ($raw['product_summary'] ?? '')) ?: $product->name,
                'stylist_confidence' => $resolved['confidence'],
                'stylist_warnings'   => $warnings,
                'product_features'   => array_values(array_intersect((array) ($raw['features'] ?? []), MockupPromptBuilder::PRODUCT_FEATURES)),
            ],
        ]);

        return $outfit->fresh();
    }

    /** @return array<int,string> */
    private function kindsFor(string $region, ?string $length): array
    {
        $long = in_array($length, ['midi', 'maxi'], true);

        return array_values(array_filter(match ($region) {
            'bottom' => ['top', 'color', 'footwear', $long ? null : 'socks', 'accessory'],
            'top'    => ['bottom', 'color', 'footwear', 'socks', 'accessory'],
            'outerwear' => ['top', 'color', 'bottom', 'footwear', 'socks', 'accessory'],
            default  => ['footwear', $long ? null : 'socks', 'accessory'],
        }));
    }

    /**
     * Çeşitlilik kuralı için aynı kategorideki son kombinlerin (tamamlayıcı parça, ayakkabı) ikilileri.
     *
     * @return array<int,array{0:?string,1:?string}>
     */
    private function recentCombos(MockupOutfit $outfit): array
    {
        // Faz 5: stilist aşamasından reddedilen kombinler bu ürün için yasak (MockupRerunService).
        $avoid = array_values(array_filter((array) ($outfit->meta['avoid_combos'] ?? []), 'is_array'));
        $categoryId = $outfit->product?->category_id;
        if (! $categoryId) {
            return $avoid;
        }

        return [...$avoid, ...MockupOutfit::query()->whereKeyNot($outfit->id)->whereNotNull('pieces')
            ->whereHas('product', fn ($q) => $q->where('category_id', $categoryId))
            ->latest('id')->limit((int) config('creative.mockup.stylist_diversity_window', 5))->get()
            ->map(fn (MockupOutfit $o) => [($o->pieces['top'] ?? $o->pieces['bottom'] ?? null), ($o->pieces['footwear'] ?? null)])
            ->all()];
    }

    private function attributeName(Product $product, string $code): ?string
    {
        $id = ($product->getAttribute('attributes') ?? [])[$code] ?? null;

        return $id && ! is_array($id) ? \Modules\Product\Models\ProductAttributeOption::whereKey($id)->value('name') : null;
    }

    private function genderWord(Product $product): ?string
    {
        return match (mb_strtolower((string) $product->gender)) {
            'kız'   => 'girl',
            'erkek' => 'boy',
            default => null,
        };
    }
}
