<?php

namespace Modules\Creative\Services\Ai;

use Illuminate\Support\Facades\Storage;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * AI sahne pipeline orchestrator'ı.
 *
 * İki aşama: 1) compose (Gemini) ile model/sahne kurgusu,
 * 2) try-on (fal idm-vton) ile ürünü modele giydirme.
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

        return [
            // Render slot'u yerel dosya ister; az önce okuduğumuz kaynak yerel
            // yol zaten geçerli (uzak diske ayrıca yüklendi).
            'path'   => $resultPath,
            'stored' => $rel,
        ];
    }
}
