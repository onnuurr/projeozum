<?php

namespace Modules\Creative\Services\Ai\Drivers\Fal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * fal.ai fashn/tryon v1.6 ile sanal giydirme (try-on) sürücüsü.
 * Katalog kullanımına özel: logo/desen/yazı sadakatini korumaya odaklı bir model,
 * bu yüzden 'mode' => 'quality' ile en yüksek sadakat modunda çalıştırılır.
 * Görseller public URL gerektirmesin diye data URI olarak gönderilir.
 */
class FalFashnTryOn implements GarmentTryOnContract
{
    public function __construct(private FalClient $client) {}

    public function tryOn(string $modelImagePath, string $garmentImagePath, array $extraGarmentImages = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string
    {
        // fashn/tryon API'si de idm-vton gibi tek "garment_image" alanı kabul eden
        // özelleşmiş bir modeldir (LLM değildir) — ek açı/detay görselleri, metin
        // talimatları ve koruma direktifleri buraya gönderilemez, güvenle yok sayılır
        // (ana görsel yine de kullanılır).
        //
        // 'garment_photo_type' => 'flat-lay' bilinçli sabitlenmiştir: bu uygulamada giysi
        // kaynağı HER ZAMAN ürün kataloğu/flat-lay fotoğrafıdır (bkz. pickGarmentSrc,
        // TryonController::store — manken üstü fotoğraf değil). 'auto' bırakılırsa model
        // bazen (özellikle tulum/elbise gibi tek parça ürünlerde) kategori/tipi yanlış
        // sınıflandırıp görseli hiç değiştirmeden döndürebiliyor.
        $input = [
            'model_image'        => ImageFile::dataUri($modelImagePath),
            'garment_image'      => ImageFile::dataUri($garmentImagePath),
            'mode'               => 'quality',
            'garment_photo_type' => 'flat-lay',
        ];

        Log::info('fal fashn/tryon isteği gönderiliyor', [
            'model'               => $this->modelIdentifier(),
            'mode'                => $input['mode'],
            'garment_photo_type'  => $input['garment_photo_type'],
        ]);

        $result = $this->client->run($input);

        Log::info('fal fashn/tryon yanıtı alındı', [
            'model'       => $this->modelIdentifier(),
            'result_keys' => array_keys($result),
            'image_count' => count($result['images'] ?? []),
        ]);

        $url = $result['images'][0]['url']
            ?? $result['image']['url']
            ?? null;

        if (! is_string($url) || $url === '') {
            Log::warning('fal fashn/tryon sonucunda görsel URL bulunamadı', ['result' => $result]);

            throw new RuntimeException('fal fashn/tryon sonucunda görsel URL bulunamadı.');
        }

        return ImageFile::temp($this->fetch($url), 'png');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.ai.fal.model');
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
