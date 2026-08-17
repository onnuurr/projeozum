<?php

namespace Modules\Creative\Services\Ai;

use App\Support\Media;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * AI sahne pipeline orchestrator'ı.
 *
 * İki aşama: 1) compose (Gemini) ile model/sahne kurgusu,
 * 2) try-on (fal fashn/tryon) ile ürünü modele giydirme.
 * Sonuç kalıcı diske yazılır; render'a beslenmek üzere yerel mutlak yol döndürülür.
 * Ara çıktılar (geçici dosyalar) her durumda temizlenir.
 */
class AiSceneService
{
    public function __construct(
        private SceneComposerContract $composer,
        private GarmentTryOnContract $tryOn,
    ) {}

    /**
     * @return array{path:string,stored:string}  path=yerel mutlak yol, stored=disk göreli yol
     */
    public function generate(SceneRequest $request, int $productId): array
    {
        $temps = [];

        try {
            $modelPath = $this->composer->compose($request);
            $temps[]   = $modelPath;

            // Ürün görseli varsa giydir; yoksa kurgu görselini sonuç kabul et.
            $garment = $request->productImagePath;
            if (is_string($garment) && is_file($garment)) {
                $resultPath = $this->tryOn->tryOn($modelPath, $garment);
                $temps[]    = $resultPath;
            } else {
                $resultPath = $modelPath;
            }

            return $this->persist($resultPath, $productId);
        } finally {
            ImageFile::delete($temps);
        }
    }

    /**
     * Sonuç görselini kalıcı diske yazar.
     *
     * @return array{path:string,stored:string}
     */
    private function persist(string $resultPath, int $productId): array
    {
        $bytes = @file_get_contents($resultPath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('AI sahne çıktısı okunamadı.');
        }

        $disk = config('creative.disk', 'public');
        $rel  = sprintf(
            '%s/%d/%s.png',
            config('creative.ai.output_dir', 'ai-scenes'),
            $productId,
            bin2hex(random_bytes(6)),
        );

        Storage::disk($disk)->put($rel, $bytes);

        // KRİTİK: $resultPath bir geçici dosyadır ve generate()'teki finally
        // bloğu (ImageFile::delete($temps)) bu fonksiyon dönüşünden hemen
        // sonra siler — o yolu döndürmek render'a artık var olmayan bir
        // dosya verirdi (Python compositor sessizce atlar, ürün görseli
        // boş kalır). Bunun yerine az önce kalıcı diske yazdığımız `$rel`i
        // yerel okunabilir yola çözüyoruz (uzak diskte kalıcı cache'e iner).
        $local = Media::localPath($rel, $disk);
        if ($local === null) {
            // Uzak diskte (S3/R2) put() sonrası exists() nadiren yarışa girip
            // false dönebilir (eventual consistency) — null'u sessizce ileri
            // taşımak yerine burada fail-fast: aksi halde compositor bu yolu
            // sessizce atlar ve ürün görseli boş kalırdı (bkz. yukarıdaki not).
            throw new RuntimeException("AI sahne çıktısı diske yazıldı ama yerel yola çözülemedi: {$rel}");
        }

        return [
            'path'   => $local,
            'stored' => $rel,
        ];
    }
}
