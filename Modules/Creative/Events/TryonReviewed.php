<?php

namespace Modules\Creative\Events;

/**
 * Bir giydirme sonucu onaylandı/reddedildi (bkz. TryonController::approve/reject).
 * generationToken, {@see TryonGenerationRendered}'daki context.generation_token ile
 * eşleşerek üretim + karar kayıtlarının Modules/TrainingIngest tarafında birleştirilmesini
 * sağlar (korelasyon anahtarı — üretim satırı yerinde güncellendiği için result_id tek
 * başına yeterli değildir, bir sonraki yeniden üretimde değişir).
 */
class TryonReviewed
{
    /**
     * @param  array<int,string>|null  $reviewTags  Karar anındaki ret sebebi etiketlerinin snapshot'ı
     */
    public function __construct(
        public readonly int $resultId,
        public readonly ?string $generationToken,
        public readonly string $verdict,
        public readonly ?array $reviewTags,
        public readonly ?string $reviewNote,
        public readonly ?int $reviewerId,
        public readonly ?\DateTimeInterface $reviewedAt,
    ) {}
}
