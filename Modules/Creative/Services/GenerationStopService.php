<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Creative\Models\AiBatchRequest;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\TryonResult;

/**
 * Arka plandaki görsel üretimlerini durdurur (Ürün Giydirme › Durdur): eski hat, mokap, yakın plan.
 *
 * Kuyruk işleri silinmez — satırın generation_token'ı yenilenir; kuyruktaki ya da o an çalışan iş
 * bitince token tutmadığı için sonucu yazmadan çıkar (GenerateOnModelJob::isCurrent, servislerin
 * $expectedToken kontrolü). Mokap kombini "failed" olur, sonraki aşamaya geçmez. Batch API: henüz
 * gönderilmemiş istekler hiç gönderilmez (ücret oluşmaz); gönderilmiş batch'in bütün istekleri
 * durduruluyorsa Google'a iptal gider (işlenmekte olan istek yine ücretlenebilir).
 */
class GenerationStopService
{
    public const REASON = 'Kullanıcı durdurdu.';

    public function __construct(private AiBatchService $batch) {}

    /** @return array{results:int, outfits:int, batch:int} */
    public function stopResult(TryonResult $result): array
    {
        return $this->stop(collect([$result])->filter(fn ($r) => $this->isActive($r)), collect());
    }

    /** @return array{results:int, outfits:int, batch:int} */
    public function stopProduct(int $productId): array
    {
        return $this->stop(
            TryonResult::where('product_id', $productId)->whereIn('status', $this->activeStatuses())->get(),
            MockupOutfit::where('product_id', $productId)->whereIn('status', [MockupOutfit::STATUS_QUEUED, MockupOutfit::STATUS_GENERATING])->get(),
        );
    }

    /** @return array{results:int, outfits:int, batch:int} */
    public function stopAll(): array
    {
        return $this->stop(
            TryonResult::whereIn('status', $this->activeStatuses())->get(),
            MockupOutfit::whereIn('status', [MockupOutfit::STATUS_QUEUED, MockupOutfit::STATUS_GENERATING])->get(),
        );
    }

    /**
     * @param  Collection<int,TryonResult>  $results
     * @param  Collection<int,MockupOutfit>  $outfits
     * @return array{results:int, outfits:int, batch:int}
     */
    private function stop(Collection $results, Collection $outfits): array
    {
        $baseIds = MockupBase::whereIn('outfit_id', $outfits->pluck('id'))->pluck('id');

        DB::transaction(function () use ($results, $outfits, $baseIds) {
            foreach ($results as $r) {
                $meta = $r->meta ?? [];
                unset($meta['mockup_run']);
                $r->update(['status' => TryonResult::STATUS_FAILED, 'error' => self::REASON,
                    'generation_token' => (string) Str::uuid(), 'meta' => $meta]);
            }
            MockupOutfit::whereIn('id', $outfits->pluck('id'))->update(['status' => MockupOutfit::STATUS_FAILED, 'error' => self::REASON]);
            MockupBase::whereIn('id', $baseIds)->whereIn('status', [MockupBase::STATUS_QUEUED, MockupBase::STATUS_GENERATING])
                ->update(['status' => MockupBase::STATUS_FAILED, 'error' => self::REASON]);
        });

        return ['results' => $results->count(), 'outfits' => $outfits->count(), 'batch' => $this->stopBatchRequests($results->pluck('id'), $baseIds)];
    }

    /** Durdurulan satırların bekleyen batch istekleri: gönderilmemişler iptal, tamamen bize ait batch'ler Google'da iptal. */
    private function stopBatchRequests(Collection $resultIds, Collection $baseIds): int
    {
        $rows = AiBatchRequest::whereIn('status', [AiBatchRequest::STATUS_QUEUED, AiBatchRequest::STATUS_SUBMITTED])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('subject_type', (new TryonResult)->getMorphClass())->whereIn('subject_id', $resultIds))
                ->orWhere(fn ($q) => $q->where('subject_type', (new MockupBase)->getMorphClass())->whereIn('subject_id', $baseIds)))
            ->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        foreach ($rows->where('status', AiBatchRequest::STATUS_SUBMITTED)->groupBy('batch_name') as $name => $mine) {
            $others = AiBatchRequest::where('batch_name', $name)->where('status', AiBatchRequest::STATUS_SUBMITTED)
                ->whereNotIn('id', $mine->pluck('id'))->exists();
            if (! $others) {
                $this->batch->cancelBatch((string) $name);
            }
        }

        foreach ($rows as $row) {
            AiBatchRequest::disk()->delete(array_filter([$row->payload_path]));
            $row->update(['status' => AiBatchRequest::STATUS_FAILED, 'error' => 'cancelled', 'payload_path' => null, 'completed_at' => now()]);
        }

        return $rows->count();
    }

    private function isActive(TryonResult $r): bool
    {
        return in_array($r->status, $this->activeStatuses(), true);
    }

    /** @return list<string> */
    private function activeStatuses(): array
    {
        return [TryonResult::STATUS_QUEUED, TryonResult::STATUS_GENERATING];
    }
}
