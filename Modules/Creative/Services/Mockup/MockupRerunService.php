<?php

namespace Modules\Creative\Services\Mockup;

use Illuminate\Support\Str;
use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\RejectionReason;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\ProductOnModelService;
use RuntimeException;

/**
 * Faz 5: reddedilen mokap sonucunu ret nedeninin AŞAMASINDAN, düzeltme talimatıyla yeniden üretir
 * ("AI ile Konuş → uygula"nın aşama bilen hali). Aşama ret etiketlerinden çözülür; birden çok aşama
 * işaretliyse akışta en yukarıdaki kazanır (stilist > baz > giydirme/birleştirme), çünkü yukarıdaki
 * yeniden üretilince aşağısı zaten yeniden üretilir. Ücretli — her zaman kullanıcı başlatır.
 *
 *  - tryon / composite: yalnız bu giydirme (talimat meta.extra_instructions'a)
 *  - base: bu beden grubunun baz görselleri (talimat outfit meta.base_correction'a) + giydirmeleri
 *  - stylist: ürünün bütün beden gruplarında kombin yeniden (önceki kombin yasaklanır) + bazlar + giydirmeler
 */
class MockupRerunService
{
    public function __construct(
        private MockupProductTypeResolver $types,
        private ProductOnModelService $onModel,
    ) {}

    /** @return array{stage:string, label:string, usd:float} */
    public function plan(TryonResult $result): array
    {
        $stage = $this->stageFor($result);
        $groups = $stage === 'stylist' ? max(1, $this->productOutfits($result)->count()) : 1;
        $usd = match ($stage) {
            'stylist' => 0.22 * $groups,
            'base'    => 0.21,
            default   => 0.036,
        };

        return ['stage' => $stage, 'label' => RejectionReason::STAGES[$stage] ?? $stage, 'usd' => round($usd, 3)];
    }

    public function stageFor(TryonResult $result): string
    {
        if (! RejectionReason::hasStage()) {
            return 'tryon';
        }
        $stages = RejectionReason::query()->whereIn('label', (array) $result->review_tags)->whereNotNull('stage')->pluck('stage')->all();
        foreach (array_keys(RejectionReason::STAGES) as $stage) {        // akış sırası: yukarıdaki kazanır
            if (in_array($stage, $stages, true)) {
                return $stage === 'mask' ? 'base' : $stage;
            }
        }

        return 'tryon';
    }

    /** Ret etiketlerinin İngilizce ipuçları + onaycının notu → modele düzeltme talimatı. */
    public function correctionFor(TryonResult $result): string
    {
        $hints = RejectionReason::query()->whereIn('label', (array) $result->review_tags)->pluck('hint')->filter()->all();
        $parts = array_map(fn ($h) => "Fix: {$h}.", $hints);
        if (trim((string) $result->review_note) !== '') {
            $parts[] = 'Reviewer note (Turkish): ' . trim((string) $result->review_note);
        }

        return trim(implode(' ', $parts));
    }

    /** @return array{stage:string, label:string, usd:float} */
    public function rerun(TryonResult $result): array
    {
        if (! $result->isMockup() || $result->review_status !== TryonResult::REVIEW_REJECTED) {
            throw new RuntimeException(__('creative::messages.mockup.rerun_not_rejected'));
        }
        $outfit = $result->mockupBase?->outfit;
        if (! $outfit) {
            throw new RuntimeException(__('creative::messages.mockup.base_missing'));
        }
        $plan = $this->plan($result);
        $correction = $this->correctionFor($result);

        // Ret, yeniden üretimde satırdan silinir; rapor (MockupReviewSummary) için geçmişe yazılır.
        $result->update(['meta' => [...($result->meta ?? []), 'rejection_history' => [
            ...((array) ($result->meta['rejection_history'] ?? [])),
            ['at' => ($result->reviewed_at ?? now())->toIso8601String(), 'tags' => array_values((array) $result->review_tags),
                'note' => $result->review_note, 'stage' => $plan['stage'], 'qa' => $result->qa['status'] ?? null,
                'outfit_id' => $outfit->id, 'mannequin_id' => $result->mannequin_id],
        ]]]);
        $result->refresh();

        match ($plan['stage']) {
            'stylist' => $this->rerunStylist($result, $outfit),
            'base'    => $this->rerunBases($outfit, $correction),
            default   => $this->rerunTryon($result, $correction),
        };
        if ($plan['stage'] !== 'tryon' && $plan['stage'] !== 'composite') {
            // Bazlar hazır olunca satır zaten yeniden kuyruğa alınır; o zamana kadar "sırada" görünsün
            // (kartta tekrar yeniden üret düğmesi çıkmasın, ret raporda iki kez sayılmasın).
            $result->update(['status' => TryonResult::STATUS_QUEUED, 'review_status' => null, 'review_note' => null,
                'reviewed_by' => null, 'reviewed_at' => null]);
        }

        return $plan;
    }

    private function rerunTryon(TryonResult $result, string $correction): void
    {
        $meta = $result->meta ?? [];
        unset($meta['mockup_run'], $meta['chat_suggested_instruction'], $meta['garment_extras']);

        // Ürün fotoğrafları / renk etiketleri ret sonrası düzeltilmiş olabilir: rengin güncel fotoğrafları alınır.
        $plan = $this->onModel->colorGarments($result->product()->with(['images', 'variants'])->firstOrFail(), $result->mockupBase?->outfit?->meta['garment_path'] ?? null);
        $garment = $plan['garments'][(string) $result->color] ?? $result->garment_image_path;
        if ($extras = $plan['extras'][(string) $result->color] ?? []) {
            $meta['garment_extras'] = array_map(fn (string $p) => ['path' => $p, 'label' => null], $extras);
        }

        $result->update([
            'garment_image_path' => $garment,
            'status' => TryonResult::STATUS_QUEUED, 'generation_token' => (string) Str::uuid(), 'error' => null, 'qa' => null,
            'meta' => [...$meta, 'extra_instructions' => $correction ?: null, 'rerun_stage' => 'tryon'],
            'review_status' => null, 'review_note' => null, 'reviewed_by' => null, 'reviewed_at' => null,
        ]);
        GenerateOnModelJob::dispatch($result->id, $result->generation_token);
    }

    private function rerunBases(MockupOutfit $outfit, string $correction): void
    {
        $this->assertIdle($outfit);
        $this->resetOutfit($outfit, ['base_correction' => $correction ?: null], keepLook: true);
        GenerateMockupJob::dispatch($outfit->id);
    }

    private function rerunStylist(TryonResult $result, MockupOutfit $outfit): void
    {
        $outfits = $this->productOutfits($result);
        $outfits->each(fn ($o) => $this->assertIdle($o));
        // Reddedilen kombin (tamamlayıcı parça + ayakkabı) yasaklanır: çeşitlilik kuralı başka ikili seçer.
        $avoid = [...((array) ($outfit->meta['avoid_combos'] ?? [])),
            [($outfit->pieces['top'] ?? $outfit->pieces['bottom'] ?? null), ($outfit->pieces['footwear'] ?? null)]];
        foreach ($outfits as $o) {
            // Stilistin TAHMİN ettiği boy yeniden çözülür (ret sonrası kategori/ürün "Boy" özelliği girilmiş
            // olabilir); hâlâ yoksa boşaltılır, stilist ürünün bütün fotoğraflarından yeniden tahmin eder.
            if ($o->length_source === 'ai') {
                $type = $this->types->resolve($o->product);   // bölge verilmez: kategori boyu da okunsun
                $o->update(['length' => $type['length'] ?? '', 'length_source' => $type['length_source']]);
            }
            $this->resetOutfit($o, ['avoid_combos' => $avoid], keepLook: false);
            GenerateMockupJob::dispatch($o->id);
        }
    }

    /** @return \Illuminate\Support\Collection<int,MockupOutfit> ürünün aynı bölgedeki (başarısız olmayan) kombinleri */
    private function productOutfits(TryonResult $result): \Illuminate\Support\Collection
    {
        $outfit = $result->mockupBase?->outfit;

        return $outfit ? MockupOutfit::where('product_id', $outfit->product_id)->where('region', $outfit->region)
            ->where('status', '!=', MockupOutfit::STATUS_FAILED)->get() : collect();
    }

    private function assertIdle(MockupOutfit $outfit): void
    {
        if (in_array($outfit->status, [MockupOutfit::STATUS_QUEUED, MockupOutfit::STATUS_GENERATING], true)) {
            throw new RuntimeException(__('creative::messages.mockup.already_running'));
        }
    }

    /** Kombini yeniden kuyruğa hazırlar: bazlar silinir (dosyalar yeni üretimde üzerine yazılır), stilist sonucu isteğe göre. */
    private function resetOutfit(MockupOutfit $outfit, array $meta, bool $keepLook): void
    {
        MockupBase::where('outfit_id', $outfit->id)->delete();
        $outfit->update([
            'status' => MockupOutfit::STATUS_QUEUED, 'error' => null,
            'meta'   => array_filter([...($outfit->meta ?? []), ...$meta], fn ($v) => $v !== null),
            ...($keepLook ? [] : ['outfit_sentence' => null, 'pieces' => null, 'poses' => null, 'reasoning_tr' => null]),
        ]);
    }
}
