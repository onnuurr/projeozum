<?php

namespace Modules\Creative\Services\Ai\Drivers\Fal;

use Illuminate\Support\Facades\Http;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * fal.ai idm-vton ile sanal giydirme (try-on) sürücüsü.
 * Görseller public URL gerektirmesin diye data URI olarak gönderilir.
 */
class FalIdmVtonTryOn implements GarmentTryOnContract
{
    public function __construct(private FalClient $client) {}

    public function tryOn(string $modelImagePath, string $garmentImagePath): string
    {
        $result = $this->client->run([
            'human_image'   => ImageFile::dataUri($modelImagePath),
            'garment_image' => ImageFile::dataUri($garmentImagePath),
        ]);

        $url = $result['image']['url']
            ?? $result['images'][0]['url']
            ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('fal idm-vton sonucunda görsel URL bulunamadı.');
        }

        return ImageFile::temp($this->fetch($url), 'png');
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
