<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\RejectionInsightContract;

/**
 * Ret analiz raporundaki etiket/sürücü/not özetini Gemini metin modeline
 * göndererek Türkçe "ne yapılabilir" önerisi üreten sürücü.
 */
class GeminiRejectionInsightGenerator implements RejectionInsightContract
{
    public function __construct(private GeminiClient $client) {}

    public function generate(array $tryonSummary, array $mannequinSummary): string
    {
        return trim($this->client->generateText($this->buildPrompt($tryonSummary, $mannequinSummary)));
    }

    private function buildPrompt(array $tryon, array $mannequin): string
    {
        $tryonBlock      = $this->summarizeBlock('Giydirme (TryonResult)', $tryon);
        $mannequinBlock  = $this->summarizeBlock('Manken', $mannequin);

        return <<<PROMPT
        Sen bir moda e-ticaret şirketinde AI görsel üretim pipeline'ını (manken + ürün
        giydirme) izleyen bir kalite mühendisisin. Aşağıda haftalık ret analiz raporunun
        özeti var. Bu veriye bakarak ekibe kısa, somut ve uygulanabilir bir "ne yapılabilir"
        değerlendirmesi yaz.

        {$tryonBlock}

        {$mannequinBlock}

        Kurallar:
        - Türkçe yaz, 3-5 kısa madde (•) halinde, toplam ~120 kelimeyi geçme.
        - Sadece veride görünen etiket/sürücü/not bilgisine dayan; uydurma sayı/isim ekleme.
        - Uygunsa şu tür önerilere yer ver: hangi ret etiketi/sürücü kombinasyonuna
          odaklanılmalı, prompt/yönlendirme değişikliği mi yoksa model/sürücü değişikliği mi
          denenmeli, veri az ise ne izlenmeli.
        - Ret sayısı 0 veya veri çok azsa bunu açıkça belirt, spekülasyon yapma.
        - SADECE öneri metnini yaz, başlık/giriş cümlesi/markdown başlığı ekleme.
        PROMPT;
    }

    private function summarizeBlock(string $title, array $summary): string
    {
        $lines = [
            "{$title}: {$summary['total_rejected']}/{$summary['total_reviewed']} reddedildi"
                . ($summary['rejection_rate'] !== null ? sprintf(' (%%%d)', $summary['rejection_rate'] * 100) : ''),
        ];

        if (($summary['tag_counts'] ?? []) !== []) {
            $tags = collect($summary['tag_counts'])->map(fn ($c, $t) => "{$t} ({$c})")->implode(', ');
            $lines[] = "Ret etiketleri: {$tags}";
        } else {
            $lines[] = 'Ret etiketleri: yok';
        }

        if (($summary['driver_breakdown'] ?? []) !== []) {
            $drivers = collect($summary['driver_breakdown'])
                ->map(fn ($d, $name) => "{$name} (toplam {$d['total']}, reddedilen {$d['rejected']}, modeller: " . (implode(', ', $d['models']) ?: '—') . ')')
                ->implode('; ');
            $lines[] = "Sürücü kırılımı: {$drivers}";
        }

        if (($summary['sample_notes'] ?? []) !== []) {
            $notes = collect($summary['sample_notes'])->take(5)->implode(' | ');
            $lines[] = "Örnek ret notları: {$notes}";
        }

        return implode("\n", $lines);
    }
}
