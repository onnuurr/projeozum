<?php

namespace Modules\Creative\Services\Vision;

/**
 * OCR kapalıyken devreye giren passthrough — hiç kelime bulamamış gibi
 * davranır. Bilerek: {@see \Modules\Creative\Services\CreativeRenderService}
 * bu durumu "doğrulama imkânsız" sayıp ai_compose motorunu hiç çalıştırmaz
 * (creative.composition.ocr.enabled=false iken PermanentRenderException).
 */
class NullTextRecognizer implements TextRecognizerContract
{
    public function recognize(string $imagePath): array
    {
        return [];
    }
}
