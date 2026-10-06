<?php

namespace Modules\Creative\Services\Mockup;

use Illuminate\Support\Facades\DB;
use Modules\Creative\Models\CategoryRegion;
use Modules\Creative\Models\CreativeSetting;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\StylistOption;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;

/**
 * Mokap ayarları (Creative ayarlar sayfası › Mokap): kategori → bölge, otomatik tetik, günlük limit,
 * varsayılan mankenler. Tek okuma noktası — pipeline, otomatik tetik ve ayarlar sayfası bunu kullanır.
 */
class MockupSettings
{
    public const AUTO_TRIGGER = 'mockup.auto_trigger';
    public const DAILY_LIMIT  = 'mockup.daily_limit';
    public const MANNEQUINS   = 'mockup.mannequins';      // {girl: id|null, boy: id|null}

    public const DEFAULT_DAILY_LIMIT = 20;

    /**
     * Kategorinin (yoksa üst kategorisinin) bölge eşleştirmesi.
     *
     * @return array{region:string, length:?string}|null
     */
    public function regionFor(?Category $category): ?array
    {
        for ($c = $category, $depth = 0; $c !== null && $depth < 5; $c = $c->parent, $depth++) {
            $row = CategoryRegion::where('category_id', $c->id)->first();
            if ($row && in_array($row->region, MockupOutfit::REGIONS, true)) {
                return ['region' => $row->region, 'length' => $row->length ?: null];
            }
        }

        return null;
    }

    public function autoTrigger(): bool
    {
        return (bool) CreativeSetting::read(self::AUTO_TRIGGER, false);
    }

    public function dailyLimit(): int
    {
        return max(0, (int) CreativeSetting::read(self::DAILY_LIMIT, self::DEFAULT_DAILY_LIMIT));
    }

    /** Bugün başlatılan mokap kombini sayısı (otomatik + elle; maliyet sınırı). */
    public function startedToday(): int
    {
        return MockupOutfit::where('created_at', '>=', now()->startOfDay())->count();
    }

    public function remainingToday(): int
    {
        return max(0, $this->dailyLimit() - $this->startedToday());
    }

    /** @return array{girl:?int, boy:?int} */
    public function mannequinIds(): array
    {
        $v = (array) CreativeSetting::read(self::MANNEQUINS, []);

        return ['girl' => isset($v['girl']) ? (int) $v['girl'] : null, 'boy' => isset($v['boy']) ? (int) $v['boy'] : null];
    }

    /** Ürünün cinsiyetine göre varsayılan (hazır + onaylı) manken; tanımsızsa null. */
    public function mannequinFor(Product $product): ?Mannequin
    {
        $ids = $this->mannequinIds();
        $id = match (mb_strtolower((string) $product->gender)) {
            'erkek' => $ids['boy'],
            'kız'   => $ids['girl'],
            default => $ids['girl'] ?? $ids['boy'],
        };

        return $id ? Mannequin::whereKey($id)->where('status', Mannequin::STATUS_READY)
            ->where('review_status', Mannequin::REVIEW_APPROVED)->first() : null;
    }

    /**
     * Kategori eşleştirmelerini kaydeder; region boş olan satırın eşleştirmesi silinir (mokap dışı).
     *
     * @param  array<int,array{category_id:int, region:?string, length:?string}>  $rows
     */
    public function saveCategoryRegions(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                if (empty($r['region'])) {
                    CategoryRegion::where('category_id', $r['category_id'])->delete();

                    continue;
                }
                CategoryRegion::updateOrCreate(['category_id' => $r['category_id']],
                    ['region' => $r['region'], 'length' => $r['length'] ?: null]);
            }
        });
    }

    /** @param array{kind:string,key:string,label_tr:string,prompt_en:string,tags?:array<string,mixed>,is_active?:bool,sort_order?:int} $data */
    public function saveOption(?StylistOption $option, array $data): StylistOption
    {
        $option ??= new StylistOption();
        $option->fill([...$data, 'tags' => $this->cleanTags($data['tags'] ?? [])])->save();

        return $option;
    }

    /** @param array<int,int> $ids */
    public function deleteOptions(array $ids): int
    {
        return StylistOption::whereIn('id', $ids)->delete();
    }

    /** @param array{mockup_regions:?array<int,string>, requires:?array<int,string>, view:string} $data */
    public function savePoseTags(Pose $pose, array $data): Pose
    {
        $pose->update([
            'mockup_regions' => array_values(array_intersect((array) ($data['mockup_regions'] ?? []), MockupOutfit::REGIONS)) ?: null,
            'requires'       => array_values((array) ($data['requires'] ?? [])) ?: null,
            'view'           => $data['view'],
        ]);

        return $pose;
    }

    /** Boş etiket listelerini atar (boş = kısıt yok). @return array<string,mixed> */
    private function cleanTags(array $tags): array
    {
        return array_filter($tags, fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false);
    }

    /** @param array{auto_trigger?:bool, daily_limit?:int, mannequins?:array{girl:?int,boy:?int}} $values */
    public function save(array $values): void
    {
        if (array_key_exists('auto_trigger', $values)) {
            CreativeSetting::write(self::AUTO_TRIGGER, (bool) $values['auto_trigger']);
        }
        if (array_key_exists('daily_limit', $values)) {
            CreativeSetting::write(self::DAILY_LIMIT, max(0, (int) $values['daily_limit']));
        }
        if (array_key_exists('mannequins', $values)) {
            CreativeSetting::write(self::MANNEQUINS, [
                'girl' => $values['mannequins']['girl'] ?? null,
                'boy'  => $values['mannequins']['boy'] ?? null,
            ]);
        }
    }
}
