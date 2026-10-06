<?php

namespace Modules\Creative\Services\Mockup;

use Illuminate\Support\Carbon;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\RejectionReason;
use Modules\Creative\Models\StylistOption;
use Modules\Creative\Models\TryonResult;

/**
 * Faz 5 — günlük ret raporunun mokap bölümü (creative:review-report):
 *  - aşama bazında ret dağılımı (ret etiketi → aşama),
 *  - otomatik kontrol ↔ insan kararı: kontrol "geçti" ama reddedildi = KAÇAN HATA; kontrol "kaldı"
 *    ama onaylandı = YANLIŞ ALARM (eşik ayarı için),
 *  - kombin (tamamlayıcı parça + ayakkabı) ve manken bazında ret oranı.
 * Retler: penceredeki güncel kararlar + yeniden üretimle satırdan silinip meta.rejection_history'ye
 * yazılanlar (bkz. MockupRerunService).
 */
class MockupReviewSummary
{
    /** @var array<string,string>|null "kind.key" => Türkçe etiket */
    private ?array $labels = null;

    /** @return array<string,mixed> */
    public function summarize(Carbon $since): array
    {
        $rows = TryonResult::query()->where('pipeline', TryonResult::PIPELINE_MOCKUP)->where('shot', TryonResult::SHOT_FULL)
            ->where(fn ($q) => $q->where('reviewed_at', '>=', $since)->orWhere('updated_at', '>=', $since))
            ->with(['mockupBase.outfit', 'mannequin:id,name'])->get();

        // Karar listesi: {verdict, tags, qa, outfit, mannequin}
        $decisions = [];
        foreach ($rows as $r) {
            foreach ((array) ($r->meta['rejection_history'] ?? []) as $h) {
                if (isset($h['at']) && Carbon::parse($h['at'])->gte($since)) {
                    $decisions[] = ['verdict' => 'rejected', 'tags' => (array) ($h['tags'] ?? []), 'qa' => $h['qa'] ?? null,
                        'outfit' => isset($h['outfit_id']) ? MockupOutfit::find($h['outfit_id']) : $r->mockupBase?->outfit,
                        'mannequin' => $r->mannequin?->name];
                }
            }
            if (in_array($r->review_status, [TryonResult::REVIEW_APPROVED, TryonResult::REVIEW_REJECTED], true) && $r->reviewed_at?->gte($since)) {
                $decisions[] = ['verdict' => $r->review_status, 'tags' => (array) $r->review_tags, 'qa' => $r->qa['status'] ?? null,
                    'outfit' => $r->mockupBase?->outfit, 'mannequin' => $r->mannequin?->name];
            }
        }

        $total = count($decisions);
        $rejected = array_values(array_filter($decisions, fn ($d) => $d['verdict'] === 'rejected'));
        $approved = array_values(array_filter($decisions, fn ($d) => $d['verdict'] === 'approved'));

        $stageOf = RejectionReason::query()->whereNotNull('stage')->pluck('stage', 'label')->all();
        $stages = [];
        foreach ($rejected as $d) {
            foreach (array_unique(array_map(fn ($t) => $stageOf[$t] ?? 'other', $d['tags'] ?: ['—'])) as $st) {
                $label = RejectionReason::STAGES[$st] ?? 'Diğer / aşamasız';
                $stages[$label] = ($stages[$label] ?? 0) + 1;
            }
        }
        arsort($stages);

        $qaMatrix = [];
        foreach ($decisions as $d) {
            $q = $d['qa'] ?? 'yok';
            $qaMatrix[$q][$d['verdict']] = ($qaMatrix[$q][$d['verdict']] ?? 0) + 1;
        }
        $missed = count(array_filter($rejected, fn ($d) => $d['qa'] === 'pass'));
        $falseAlarm = count(array_filter($approved, fn ($d) => $d['qa'] === 'fail'));

        return [
            'total_reviewed' => $total,
            'total_rejected' => count($rejected),
            'rejection_rate' => $total > 0 ? round(count($rejected) / $total, 3) : null,
            'stage_counts'   => $stages,
            'qa_vs_human'    => [
                'matrix'           => $qaMatrix,
                'missed'           => $missed,          // kontrol geçti, insan reddetti
                'missed_rate'      => count($rejected) > 0 ? round($missed / count($rejected), 3) : null,
                'false_alarm'      => $falseAlarm,      // kontrol kaldı, insan onayladı
                'false_alarm_rate' => count($approved) > 0 ? round($falseAlarm / count($approved), 3) : null,
            ],
            'by_combo'       => $this->breakdown($decisions, fn ($d) => $this->comboLabel($d['outfit'])),
            'by_mannequin'   => $this->breakdown($decisions, fn ($d) => $d['mannequin'] ?? '—'),
        ];
    }

    /** @return array<string,array{total:int,rejected:int,rate:float}> ret oranı yüksekten düşüğe */
    private function breakdown(array $decisions, callable $key): array
    {
        $out = [];
        foreach ($decisions as $d) {
            $k = $key($d);
            $out[$k]['total'] = ($out[$k]['total'] ?? 0) + 1;
            $out[$k]['rejected'] = ($out[$k]['rejected'] ?? 0) + ($d['verdict'] === 'rejected' ? 1 : 0);
        }
        foreach ($out as $k => $v) {
            $out[$k]['rate'] = round($v['rejected'] / max(1, $v['total']), 3);
        }
        uasort($out, fn ($a, $b) => [$b['rate'], $b['total']] <=> [$a['rate'], $a['total']]);

        return $out;
    }

    private function comboLabel(?MockupOutfit $o): string
    {
        if (! $o || ! $o->pieces) {
            return '—';
        }
        $labels = $this->labels ??= StylistOption::query()->get(['kind', 'key', 'label_tr'])
            ->mapWithKeys(fn ($x) => ["{$x->kind}.{$x->key}" => $x->label_tr])->all();
        $comp = isset($o->pieces['top']) ? "top.{$o->pieces['top']}" : (isset($o->pieces['bottom']) ? "bottom.{$o->pieces['bottom']}" : null);
        $foot = isset($o->pieces['footwear']) ? "footwear.{$o->pieces['footwear']}" : null;

        return implode(' + ', array_filter([$comp ? ($labels[$comp] ?? $comp) : null, $foot ? ($labels[$foot] ?? $foot) : null])) ?: '—';
    }
}
