<?php

namespace Modules\PatternEngine\Contracts;

use Illuminate\Support\Collection;
use Modules\PatternEngine\DTOs\DigitizeSuggestionDTO;
use Modules\PatternEngine\DTOs\GeneratePatternRequestDTO;
use Modules\PatternEngine\DTOs\GradedPatternResultDTO;
use Modules\PatternEngine\DTOs\GradePatternRequestDTO;
use Modules\PatternEngine\DTOs\PatternProfileDTO;
use Modules\PatternEngine\DTOs\PatternProfileSummaryDTO;
use Modules\PatternEngine\DTOs\PatternResultDTO;
use Modules\PatternEngine\DTOs\SaveProfileRequestDTO;

/**
 * Kalıp üretim soyutlaması.
 *
 * Gerçek üretim ayrı bir Python/FastAPI servisinde (pattern-core, framework
 * bağımsız); bu sözleşme Laravel tarafını ondan ayırır. Sürücü config ile
 * değişir (http|mock) — bkz. Atelier'in `PdfDxfConverterContract`'ı, aynı desen.
 */
interface PatternEngineClient
{
    /**
     * Bir `DesignParams` + beden/profil için doğrulanmış kalıp üretir.
     */
    public function generate(GeneratePatternRequestDTO $request): PatternResultDTO;

    /**
     * Taban beden + hedef bedenler için gradasyonlu kalıp üretir (§4.5, Sprint 3).
     */
    public function gradeGenerate(GradePatternRequestDTO $request): GradedPatternResultDTO;

    /**
     * Servis ayakta mı? Ulaşılamıyorsa `false` döner — çağıran akışı engellemez.
     */
    public function probe(): bool;

    /**
     * Var olan tüm profillerin özeti (§5.2 çok profillilik, Sprint 4).
     *
     * DTO sınırında liste dönüşü `Collection` ile yapılır — bkz.
     * `Modules\Marketplace\Contracts\MarketplaceClient::fetchOrders()`, aynı desen.
     *
     * @return Collection<int,PatternProfileSummaryDTO>
     */
    public function listProfiles(): Collection;

    /**
     * Bir profilin tam ölçü tablosu; yoksa `null` (404).
     */
    public function getProfile(string $name): ?PatternProfileDTO;

    /**
     * Yeni bir profil yaratır ya da var olanı günceller (upsert).
     *
     * @throws \Modules\PatternEngine\Exceptions\ProfileProtectedException  `ts_default` gibi referans profiller için
     */
    public function saveProfile(SaveProfileRequestDTO $request): PatternProfileDTO;

    /**
     * Technical flat görselinden güven skorlu `DesignParams` önerisi çıkarır (§4.1, Sprint 5).
     *
     * AI omurga değil sağlayıcıdır (§P2) — bu asla otomatik kalıp üretmez, yalnızca
     * `generate()`'e girdi olacak bir öneri döner; kullanıcı onayı/düzeltmesi gerekir.
     */
    public function digitize(string $garment, string $size, string $profile, string $absoluteImagePath): DigitizeSuggestionDTO;
}
