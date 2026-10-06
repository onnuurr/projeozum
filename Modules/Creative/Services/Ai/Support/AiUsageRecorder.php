<?php

namespace Modules\Creative\Services\Ai\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Contracts\AiUsageStoreContract;

/**
 * AI çağrı maliyet kaydı. GeminiClient ve FalClient her BAŞARILI çağrıdan sonra record() çağırır;
 * çağrının hangi işe ait olduğu forSubject() ile kurulan bağlamdan gelir (iç içe olabilir:
 * mokap kombini → baz görsel). Kayıt başarısız olursa üretim ASLA bozulmaz (yalnız loglanır).
 * Maliyet tahminidir: config('creative.ai.pricing').
 *
 * Container'da scoped bağlanır → kuyruk worker'ında her işte bağlam sıfırlanır.
 */
class AiUsageRecorder
{
    /** @var array<int,Model> */
    private array $subjects = [];

    /**
     * @template T
     * @param  Closure():T  $fn
     * @return T
     */
    public function forSubject(?Model $subject, Closure $fn): mixed
    {
        if ($subject === null) {
            return $fn();
        }
        $this->subjects[] = $subject;
        try {
            return $fn();
        } finally {
            array_pop($this->subjects);
        }
    }

    /**
     * Gemini generateContent yanıtı için kayıt.
     *
     * @param  array<string,mixed>  $payload   İstek gövdesi (imageConfig.imageSize buradan)
     * @param  array<string,mixed>  $response  Yanıt JSON'u (usageMetadata buradan)
     * @param  bool  $batch  Batch API yanıtı: tüm kalemler pricing.batch_multiplier ile (%50), işlem adı "_batch" ekli
     */
    public function recordGemini(string $operation, string $model, array $payload, array $response, bool $batch = false): void
    {
        $this->safely(function () use ($operation, $model, $payload, $response, $batch) {
            $price = $this->geminiPrice($model);
            $usage = (array) ($response['usageMetadata'] ?? []);
            $in  = (int) ($usage['promptTokenCount'] ?? 0);
            $out = (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0);

            $images = 0;
            foreach ((array) ($response['candidates'][0]['content']['parts'] ?? []) as $part) {
                if (isset($part['inlineData']) || isset($part['inline_data'])) {
                    $images++;
                }
            }

            $size = null;
            $cost = $in / 1e6 * (float) ($price['input_per_mtok'] ?? 0);
            if ($images > 0 && isset($price['image'])) {
                $size = (string) ($payload['generationConfig']['imageConfig']['imageSize'] ?? ($price['default_size'] ?? '1K'));
                $cost += $images * (float) ($price['image'][$size] ?? max($price['image']));
                // Görsel çağrısı da düşünür / metin yazar; bunlar görsel değil metin fiyatıyla
                // faturalanır (≈ %1). Bkz. nonImageOutputTokens().
                $cost += $this->nonImageOutputTokens($usage) / 1e6 * (float) ($price['text_output_per_mtok'] ?? 0);
            } else {
                $cost += $out / 1e6 * (float) ($price['output_per_mtok'] ?? 0);
            }

            if ($batch) {
                $cost *= (float) config('creative.ai.pricing.batch_multiplier', 0.5);
                $operation .= '_batch';
            }

            $this->store('gemini', $model, $operation, $size, $in, $out, $images, $cost);
        });
    }

    /**
     * Görsel çağrısının görsel olmayan çıktısı: toplam aday token'ı − görsel token'ları + düşünme.
     * Gerçek yanıtta candidatesTokensDetails yalnız IMAGE satırı taşıyor; metin/düşünme farkı
     * (1K'da ~300 token) ayrıntıda görünmüyor ama faturada "Text Output" olarak geliyor (2026-10-06).
     * Ayrıntı yoksa yalnız düşünme sayılır (görsel token'ı metin fiyatıyla sayılmasın).
     *
     * @param  array<string,mixed>  $usage
     */
    private function nonImageOutputTokens(array $usage): int
    {
        $thoughts = (int) ($usage['thoughtsTokenCount'] ?? 0);
        $details = (array) ($usage['candidatesTokensDetails'] ?? []);
        if ($details === []) {
            return $thoughts;
        }

        $image = 0;
        foreach ($details as $detail) {
            if (strtoupper((string) ($detail['modality'] ?? '')) === 'IMAGE') {
                $image += (int) ($detail['tokenCount'] ?? 0);
            }
        }

        return max(0, (int) ($usage['candidatesTokenCount'] ?? 0) - $image) + $thoughts;
    }

    public function recordFal(string $model): void
    {
        $this->safely(function () use ($model) {
            $prices = (array) config('creative.ai.pricing.fal', []);
            $this->store('fal', $model, 'image', null, 0, 0, 1, (float) ($prices[$model] ?? $prices['default'] ?? 0));
        });
    }

    /** @return array<string,mixed> */
    private function geminiPrice(string $model): array
    {
        $table = (array) config('creative.ai.pricing.gemini', []);
        if (isset($table[$model])) {
            return $table[$model];
        }
        // "...-preview" / sürüm ekli adlar en uzun önek eşleşmesiyle bulunur.
        $match = collect(array_keys($table))->filter(fn ($k) => str_starts_with($model, $k))->sortByDesc(fn ($k) => strlen($k))->first();

        return $match ? $table[$match] : [];
    }

    private function store(string $provider, string $model, string $operation, ?string $size, int $in, int $out, int $images, float $cost): void
    {
        if (! app()->bound(AiUsageStoreContract::class)) {
            return;
        }
        $subject = end($this->subjects) ?: null;
        app(AiUsageStoreContract::class)->store([
            'provider'      => $provider,
            'model'         => $model,
            'operation'     => $operation,
            'image_size'    => $size,
            'input_tokens'  => $in,
            'output_tokens' => $out,
            'images_out'    => $images,
            'est_cost_usd'  => round($cost, 5),
            'subject_type'  => $subject?->getMorphClass(),
            'subject_id'    => $subject?->getKey(),
        ]);
    }

    private function safely(Closure $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('AI maliyet kaydı yazılamadı.', ['error' => $e->getMessage()]);
        }
    }
}
