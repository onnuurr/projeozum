<?php

namespace Modules\Creative\Services\Ai\Drivers\Fal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * fal.ai flux-pro/v1/vto ile sanal giydirme (try-on) sürücüsü.
 *
 * fashn/tryon'un aksine bu model bir "warp" API'si değil, PROMPT tabanlı bir
 * FLUX modeli — tek zorunlu metin alanı ('prompt') sayesinde protect list ve
 * ret-sonrası düzeltme talimatı (Gemini sürücüsündeki gibi) burada gerçekten
 * kullanılabilir, güvenle yok sayılmaz. Yine de ek açı/detay görselleri
 * (extraGarmentImages) desteklenmez — API tek 'garment_image_url' alanı kabul
 * eder, bu yüzden ana görsel dışındakiler yok sayılır.
 *
 * Görseller public URL gerektirmesin diye data URI olarak gönderilir (fal
 * platformu tüm '*_url' alanlarında base64 data URI'yi de kabul eder).
 */
class FalFluxVtoTryOn implements GarmentTryOnContract
{
    public function __construct(private FalClient $client) {}

    public function tryOn(string $modelImagePath, string $garmentImagePath, array $extraGarmentImages = [], ?string $extraInstruction = null, ?string $protectListSentence = null, ?array $context = null): string
    {
        $input = [
            'human_image_url'   => ImageFile::dataUri($modelImagePath),
            'garment_image_url' => ImageFile::dataUri($garmentImagePath),
            'prompt'            => $this->buildPrompt($protectListSentence, $extraInstruction),
            'output_format'     => 'png',
        ];

        Log::info('fal flux-pro/vto isteği gönderiliyor', [
            'model'  => $this->modelIdentifier(),
            'prompt' => $input['prompt'],
        ]);

        $result = $this->client->run($input);

        Log::info('fal flux-pro/vto yanıtı alındı', [
            'model'       => $this->modelIdentifier(),
            'result_keys' => array_keys($result),
            'image_count' => count($result['images'] ?? []),
        ]);

        $url = $result['images'][0]['url'] ?? null;

        if (! is_string($url) || $url === '') {
            Log::warning('fal flux-pro/vto sonucunda görsel URL bulunamadı', ['result' => $result]);

            throw new RuntimeException('fal flux-pro/vto sonucunda görsel URL bulunamadı.');
        }

        return ImageFile::temp($this->fetch($url), 'png');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.ai.fal.model');
    }

    /**
     * Kısa/doğal bir sahne betimlemesiyle başlar (bu model uzun, çok maddeli
     * paragraf yönergelere değil, tek-iki cümlelik doğal betimlemelere göre
     * eğitilmiş — bkz. fal.ai örnek prompt'u: "The t-shirt is worn tucked
     * in."), ardından ürün sadakati kısıtını ekler. protectListSentence
     * verilmişse (GarmentIdentityRuleEngine çıktısı) en başa, extraInstruction
     * (ret sonrası sohbet düzeltmesi) verilmişse en sona eklenir — Gemini
     * sürücüsüyle aynı yerleşim mantığı.
     */
    private function buildPrompt(?string $protectListSentence, ?string $extraInstruction): string
    {
        $parts = [];

        if ($protectListSentence !== null && trim($protectListSentence) !== '') {
            $parts[] = trim($protectListSentence);
        }

        $parts[] = 'Full-body, front-facing studio photo. Dress the person naturally in the garment, matching how it would realistically drape, fold and fit on the body.';
        $parts[] = "Preserve the garment's exact color, pattern, print, logo, text, fabric and hardware exactly as shown in its reference photo — no restyling, recoloring or redesign.";
        $parts[] = 'Keep the person, pose, framing, lighting and background unchanged.';

        if ($extraInstruction !== null && trim($extraInstruction) !== '') {
            $parts[] = 'IMPORTANT correction: the previous attempt was rejected for this exact reason, fix it in this generation: ' . trim($extraInstruction);
        }

        return implode(' ', $parts);
    }

    private function fetch(string $url): string
    {
        // fal çıktısı bir data URI ise doğrudan çöz; değilse indir.
        if (str_starts_with($url, 'data:')) {
            $comma = strpos($url, ',');
            $bin   = $comma !== false ? base64_decode(substr($url, $comma + 1), true) : false;
            if ($bin === false || $bin === '') {
                throw new RuntimeException('fal data URI çözümlenemedi.');
            }

            return $bin;
        }

        $response = Http::timeout((int) config('creative.ai.timeout', 240))->get($url);
        if ($response->failed()) {
            throw new RuntimeException("fal çıktı görseli indirilemedi (HTTP {$response->status()}).");
        }

        return $response->body();
    }
}
