<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\RejectionInsightContract;

/**
 * Gemini anahtarı yokken devreye giren, veri özetini düz metne çeviren
 * şablon tabanlı sürücü (bkz. GeminiRejectionInsightGenerator).
 */
class MockRejectionInsightGenerator implements RejectionInsightContract
{
    public function generate(array $tryonSummary, array $mannequinSummary): string
    {
        $lines = [];

        foreach (['Giydirme' => $tryonSummary, 'Manken' => $mannequinSummary] as $label => $s) {
            if (($s['total_reviewed'] ?? 0) === 0) {
                $lines[] = "• {$label}: bu pencerede incelenen kayıt yok, öneri üretilemedi.";
                continue;
            }

            if (($s['total_rejected'] ?? 0) === 0) {
                $lines[] = "• {$label}: ret yok ({$s['total_reviewed']} inceleme), izlemeye devam.";
                continue;
            }

            $topTag = array_key_first($s['tag_counts'] ?? []);
            $lines[] = $topTag !== null
                ? "• {$label}: en sık ret nedeni \"{$topTag}\" ({$s['tag_counts'][$topTag]} kez) — önce bu alana odaklanılmalı."
                : "• {$label}: {$s['total_rejected']}/{$s['total_reviewed']} reddedildi ama etiket kaydı yok.";
        }

        $lines[] = '• Bu, Gemini anahtarı tanımsız olduğu için üretilen şablon özetidir — GEMINI_API_KEY ile detaylı AI önerisi alınabilir.';

        return implode("\n", $lines);
    }
}
