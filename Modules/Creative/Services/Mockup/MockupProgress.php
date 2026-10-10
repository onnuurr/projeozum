<?php

namespace Modules\Creative\Services\Mockup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\TryonResult;

/**
 * Ürün Giydirme sayfasındaki "Arka planda çalışan mokaplar" paneli (salt okuma, AI'sız).
 *
 * Giydirme sonuç kartları yalnız son aşamada oluşur; öncesindeki kombin → ana kare → pozlar aşamaları
 * (Batch API'de her tur ~3 dk) sayfada görünmüyordu. Aşama kombinin, bazlarının ve sonuçlarının
 * durumundan türetilir:
 *   1 Kombin (stilist parçaları seçmedi) → 2 Ana kare → 3 Pozlar (x/y) → 4 Giydirme (x/y) → bitti.
 * Biten / hata alan kombin RECENT_MINUTES boyunca listede kalır (kullanıcı bittiğini görsün).
 */
class MockupProgress
{
    public const RECENT_MINUTES = 15;

    public const STEPS = 4;

    /** @return list<array<string,mixed>> */
    public function active(): array
    {
        $outfits = MockupOutfit::query()
            ->with(['product:id,name', 'mannequin:id,name'])
            ->where(fn ($q) => $q->whereIn('status', [MockupOutfit::STATUS_QUEUED, MockupOutfit::STATUS_GENERATING])
                ->orWhere('updated_at', '>=', now()->subMinutes(self::RECENT_MINUTES))
                ->orWhereHas('bases', fn ($b) => $b->whereHas('results', fn ($r) => $r->whereIn('status', [TryonResult::STATUS_QUEUED, TryonResult::STATUS_GENERATING])
                    ->orWhere('updated_at', '>=', now()->subMinutes(self::RECENT_MINUTES)))))
            ->latest('id')->limit(30)->get();

        if ($outfits->isEmpty()) {
            return [];
        }

        $bases = MockupBase::query()->whereIn('outfit_id', $outfits->pluck('id'))->get(['id', 'outfit_id', 'derived_from_base_id', 'status'])->groupBy('outfit_id');
        $results = TryonResult::query()->whereIn('mockup_base_id', $bases->flatten()->pluck('id'))
            ->get(['id', 'mockup_base_id', 'status', 'meta', 'reviewed_at', 'updated_at'])->groupBy('mockup_base_id');

        return $outfits->map(fn (MockupOutfit $o) => $this->row($o, $bases->get($o->id, collect()), $results))
            ->filter(fn ($row) => $row['state'] === 'running' || $row['finished_at'] === null
                || $row['finished_at']->gte(now()->subMinutes(self::RECENT_MINUTES)))
            ->map(fn ($row) => [...$row, 'finished_at' => $row['finished_at']?->toIso8601String()])
            ->values()->all();
    }

    /** @param  Collection<int,MockupBase>  $bases */
    private function row(MockupOutfit $o, Collection $bases, Collection $resultsByBase): array
    {
        $results = $bases->flatMap(fn (MockupBase $b) => $resultsByBase->get($b->id, collect()));
        $master = $bases->firstWhere('derived_from_base_id', null);
        $derived = $bases->whereNotNull('derived_from_base_id');
        $count = fn (Collection $c, string ...$st) => $c->whereIn('status', $st)->count();
        $resultsDone = $count($results, TryonResult::STATUS_DONE, TryonResult::STATUS_FAILED);

        [$step, $label, $done, $total] = match (true) {
            $o->pieces === null                                       => [1, 'Kombin seçiliyor', 0, 0],
            ! $master || $master->status !== MockupBase::STATUS_READY => [2, 'Ana kare üretiliyor', 0, 1],
            $count($derived, MockupBase::STATUS_READY, MockupBase::STATUS_FAILED) < $derived->count()
                => [3, 'Pozlar üretiliyor', $count($derived, MockupBase::STATUS_READY), $derived->count()],
            $results->isEmpty() || $resultsDone < $results->count()   => [4, 'Giydiriliyor', $count($results, TryonResult::STATUS_DONE), $results->count()],
            default                                                   => [self::STEPS + 1, 'Tamamlandı — onay bekliyor', $results->count(), $results->count()],
        };

        $failed = $o->status === MockupOutfit::STATUS_FAILED;
        $finished = $failed || $step > self::STEPS;

        return [
            'outfit_id'    => $o->id,
            'product_id'   => $o->product_id,
            'product_name' => $o->product?->name,
            'mannequin'    => $o->mannequin?->name,
            'sizes'        => $o->meta['sizes'] ?? null,
            'batch'        => (bool) ($o->meta['batch'] ?? false),
            'state'        => $failed ? 'failed' : ($finished ? 'done' : 'running'),
            'step'         => min($step, self::STEPS),
            'steps'        => self::STEPS,
            'label'        => $failed ? 'Hata: ' . ($o->error ?: 'bilinmeyen') : $label,
            'done'         => $done,
            'total'        => $total,
            // 0–100: tamamlanan aşamalar + içinde bulunulan aşamanın kesri
            'percent'      => $finished && ! $failed ? 100 : (int) round((($step - 1) + ($total > 0 ? $done / $total : 0)) / self::STEPS * 100),
            'started_at'   => $o->created_at?->toIso8601String(),
            'finished_at'  => $finished ? $this->finishedAt($o, $results) : null,
        ];
    }

    /**
     * Üretimin bittiği an: sonuçların meta.generated_at'ı; o alan eklenmeden önceki sonuçlarda incelenmemiş
     * sonucun updated_at'ı (onay/ret updated_at'ı kaydırır), o da yoksa kombinin son güncellenmesi.
     */
    private function finishedAt(MockupOutfit $o, Collection $results): Carbon
    {
        $times = $results->map(fn (TryonResult $r) => match (true) {
            ! empty($r->meta['generated_at']) => Carbon::parse($r->meta['generated_at']),
            $r->reviewed_at === null          => $r->updated_at,
            default                           => null,
        })->filter();

        return $times->max() ?? $o->updated_at;
    }
}
