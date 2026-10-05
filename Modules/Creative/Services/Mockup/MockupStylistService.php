<?php

namespace Modules\Creative\Services\Mockup;

use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder;
use Modules\Product\Models\Product;
use RuntimeException;

/**
 * Ürüne özel kombini ve pozları kurar. AI'nın önerisi doğrudan kullanılmaz: poz anahtarları
 * izinli listeye karşı doğrulanır, boy yalnız ürün özelliği yoksa AI'dan alınır, eksik alanlar
 * güvenli varsayılanla doldurulur. (Faz 3'te seçenek listeleri + kural motoru bunun yerini alacak.)
 */
class MockupStylistService
{
    public function __construct(private MockupGeneratorContract $generator) {}

    /** Hazır ve mokapta kullanılabilir pozlar (ana kare hariç): pose_key => prompt. */
    public function allowedPoses(): array
    {
        $excluded = [...(array) config('creative.mockup.excluded_poses', []), (string) config('creative.mockup.master_pose')];

        return Pose::query()->where('status', Pose::STATUS_READY)->whereNotIn('pose_key', $excluded)
            ->whereNotNull('preview_image_path')->orderBy('sort_order')
            ->pluck('prompt', 'pose_key')->all();
    }

    public function style(MockupOutfit $outfit, string $productPhotoPath): MockupOutfit
    {
        $product = $outfit->product;
        $poses = $this->allowedPoses();
        $extra = min((int) config('creative.mockup.extra_poses', 2), count($poses));

        $raw = $this->generator->stylist($productPhotoPath, [
            'region'      => $outfit->region,
            'length'      => $outfit->length ?: 'unknown',
            'age'         => $this->attributeName($product, 'yas_grubu'),
            'gender'      => $this->genderWord($product),
            'poses'       => $poses,
            'extra_poses' => $extra,
        ]);

        $sentence = trim((string) ($raw['outfit_sentence'] ?? ''));
        if ($sentence === '') {
            throw new RuntimeException(__('creative::messages.mockup.stylist_invalid'));
        }

        $chosen = array_values(array_unique(array_filter((array) ($raw['poses'] ?? []), fn ($k) => is_string($k) && isset($poses[$k]))));
        foreach (array_keys($poses) as $k) {            // eksikse sıradaki izinli pozla tamamla
            if (count($chosen) >= $extra) {
                break;
            }
            if (! in_array($k, $chosen, true)) {
                $chosen[] = $k;
            }
        }

        $length = $outfit->length;
        if (! $length) {
            $length = in_array($raw['length'] ?? null, MockupOutfit::LENGTHS, true) ? $raw['length'] : 'knee';
        }

        $outfit->update([
            'length'                 => $length,
            'pieces'                 => is_array($raw['outfit_pieces'] ?? null) ? $raw['outfit_pieces'] : null,
            'outfit_sentence'        => $sentence,
            'poses'                  => array_slice($chosen, 0, $extra),
            'reasoning_tr'           => trim((string) ($raw['reasoning_tr'] ?? '')) ?: null,
            'stylist_prompt_version' => MockupPromptBuilder::STYLIST_VERSION,
            'meta'                   => [...($outfit->meta ?? []), 'product_summary' => trim((string) ($raw['product_summary'] ?? '')) ?: $product->name],
        ]);

        return $outfit->fresh();
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
