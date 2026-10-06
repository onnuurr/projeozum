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
 * manken havuzu + ürüne yaş/cinsiyet/rotasyonla manken seçimi. Tek okuma noktası — pipeline, otomatik tetik ve ayarlar sayfası bunu kullanır.
 */
class MockupSettings
{
    public const AUTO_TRIGGER = 'mockup.auto_trigger';
    public const DAILY_LIMIT  = 'mockup.daily_limit';
    public const MANNEQUINS   = 'mockup.mannequins';      // mokapta kullanılacak manken id'leri; boş = onaylı hepsi

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

    /** @return list<int> mokapta kullanılacak mankenler; boş = hazır + onaylı hepsi */
    public function mannequinIds(): array
    {
        $v = (array) CreativeSetting::read(self::MANNEQUINS, []);
        // Eski biçim {girl, boy} (cinsiyete göre varsayılan, 2026-10-06 ilk sürüm) havuz kısıtı değildi →
        // yok sayılır (onaylı hepsi); ayar yeni listeyle kaydedilince düzelir.
        if (! array_is_list($v)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($v, 'is_numeric')));
    }

    /** @return \Illuminate\Support\Collection<int,Mannequin> */
    public function mannequinPool(): \Illuminate\Support\Collection
    {
        $ids = $this->mannequinIds();

        return Mannequin::query()->where('status', Mannequin::STATUS_READY)->where('review_status', Mannequin::REVIEW_APPROVED)
            ->whereNotNull('reference_image_path')->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))->orderBy('id')->get();
    }

    /**
     * Ürüne manken seçer: cinsiyeti uyan (ürün cinsiyeti boşsa hepsi) mankenlerden yaş aralığı ürünün
     * yaşına (varyant bedenleri: "14 Yaş") en yakın olanlar; eşitler arasında son 30 günde en az
     * kullanılan (rotasyon). Tam uyan yaş yoksa en yakını + onaycıya not.
     *
     * @return array{mannequin:Mannequin, note:?string}|null
     */
    public function pickMannequin(Product $product): ?array
    {
        $gender = match (mb_strtolower((string) $product->gender)) {
            'kız'   => 'female',
            'erkek' => 'male',
            default => null,
        };
        $pool = $this->mannequinPool()->filter(fn (Mannequin $m) => $gender === null || in_array($m->gender, [$gender, null, ''], true))->values();
        if ($pool->isEmpty()) {
            return null;
        }

        $ages = $this->productAges($product);
        $dist = fn (Mannequin $m) => $ages === null ? 0 : $this->ageDistance((string) $m->age_range, $ages[0], $ages[1]);
        $best = $pool->min($dist);
        $tied = $pool->filter(fn ($m) => $dist($m) === $best);
        $usage = MockupOutfit::query()->where('created_at', '>=', now()->subDays(30))->whereIn('mannequin_id', $tied->pluck('id'))
            ->selectRaw('mannequin_id, count(*) as n')->groupBy('mannequin_id')->pluck('n', 'mannequin_id');
        $chosen = $tied->sortBy(fn (Mannequin $m) => ((int) ($usage[$m->id] ?? 0)) * 1_000_000 + $m->id)->first();

        $note = $best > 0 ? sprintf('Ürün %s yaş; tam uyan manken yok, en yakın kullanıldı: %s (%s yaş).',
            $ages[0] === $ages[1] ? $ages[0] : "{$ages[0]}–{$ages[1]}", $chosen->name, $chosen->age_range ?: '?') : null;

        return ['mannequin' => $chosen, 'note' => $note];
    }

    /** Varyant bedenlerinden yaş aralığı ("3 Yaş" … "15 Yaş"); yaş bedeni yoksa null. @return array{0:int,1:int}|null */
    public function productAges(Product $product): ?array
    {
        $ages = [];
        foreach ($product->variants()->pluck('size') as $size) {
            if (preg_match('/(\d+)\s*ya[şs]/iu', (string) $size, $m)) {
                $ages[] = (int) $m[1];
            }
        }

        return $ages === [] ? null : [min($ages), max($ages)];
    }

    /** Manken yaş aralığı ("10-13", "45+") ile ürün aralığı arasındaki yıl farkı (0 = örtüşüyor). */
    private function ageDistance(string $range, int $lo, int $hi): int
    {
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($range), $m)) {
            [$a, $b] = [(int) $m[1], (int) $m[2]];
        } elseif (preg_match('/^(\d+)\s*\+$/', trim($range), $m)) {
            [$a, $b] = [(int) $m[1], 200];
        } else {
            return 100;     // yaşı belirsiz manken en sona
        }

        return max(0, $a - $hi, $lo - $b);
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

    /** @param array{auto_trigger?:bool, daily_limit?:int, mannequins?:list<int>} $values */
    public function save(array $values): void
    {
        if (array_key_exists('auto_trigger', $values)) {
            CreativeSetting::write(self::AUTO_TRIGGER, (bool) $values['auto_trigger']);
        }
        if (array_key_exists('daily_limit', $values)) {
            CreativeSetting::write(self::DAILY_LIMIT, max(0, (int) $values['daily_limit']));
        }
        if (array_key_exists('mannequins', $values)) {
            CreativeSetting::write(self::MANNEQUINS, array_values(array_map('intval', (array) $values['mannequins'])));
        }
    }
}
