<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\MannequinComposerContract;
use Modules\Creative\Services\Ai\MannequinRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini ile sanal manken kimlik görseli üreten compose sürücüsü.
 */
class GeminiMannequinComposer implements MannequinComposerContract
{
    public function __construct(
        private GeminiClient $client,
        private MannequinPromptBuilder $prompts,
    ) {}

    public function compose(MannequinRequest $request): string
    {
        $prompt = $this->prompts->build($request);

        // Referans fotoğraf verilmişse (bkz. MannequinPromptBuilder) Gemini'ye
        // görsel olarak da gönderilir — image-to-image gerçekçilik/ışık-doku
        // çapası, salt metinden çok daha güvenilir "gerçek insan" sonucu verir.
        $referenceImages = $request->referencePhotoPath ? [$request->referencePhotoPath] : [];

        // Kimlik (yüz) burada doğduğu için: opsiyonel güçlü model + tam boy kadraj
        // (aspectRatio) / çözünürlük. Değerler boşsa generateImage bunları göndermez.
        $bytes = $this->client->generateImage(
            $prompt,
            $referenceImages,
            (string) config('creative.ai.gemini.mannequin_model') ?: null,
            GeminiClient::defaultImageConfig(),
        );

        return ImageFile::temp($bytes, 'png');
    }
}
