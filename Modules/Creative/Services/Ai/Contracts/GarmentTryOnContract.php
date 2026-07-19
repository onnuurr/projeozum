<?php

namespace Modules\Creative\Services\Ai\Contracts;

interface GarmentTryOnContract
{
    /**
     * Ürünü (giysiyi) model görseline giydirir.
     *
     * @param  string  $modelImagePath    Model/sahne görselinin yerel yolu (compose çıktısı)
     * @param  string  $garmentImagePath  Ürün/giysi görselinin (ana/ön) yerel yolu
     * @param  array<int,array{path:string,label:?string,statement?:?string,priority?:?string,protect?:bool}>  $extraGarmentImages
     *         Opsiyonel detay görselleri (arkadan/yandan/yaka-dikiş/kumaş vb.) — AYRI bir
     *         giysi değil, AYNI ürünün ek açı/detay referanslarıdır. `statement`/`priority`/
     *         `protect` {@see \Modules\Creative\Services\GarmentIdentityRuleEngine}'in ürettiği,
     *         confidence-filtrelenmiş insan-okunur direktiflerdir (bkz. ROADMAP.md Faz G.5) —
     *         her zaman DOLU olması gerekmez (ör. eski usul manuel yükleme, ya da analiz
     *         kapalı). Çoklu görsel girişini destekleyen sürücüler (ör. Gemini) bunları ek
     *         referans/direktif olarak kullanır; desteklemeyenler (ör. tek-görsel API'li
     *         fal fashn/tryon) güvenle yok sayar.
     * @param  ?string  $extraInstruction  Ret sonrası sohbetten çıkan düzeltme talimatı
     *         (bkz. ReviewChatService). Prompt tabanlı sürücüler (ör. Gemini) bunu prompt'a
     *         ekler; sabit warping API'li sürücüler (ör. fal fashn/tryon) güvenle yok sayar.
     * @param  ?string  $protectListSentence  {@see \Modules\Creative\Services\GarmentIdentityRuleEngine::protectListSentence()}
     *         çıktısı — kritik/yüksek öncelikli parçaları adlandıran tek cümle, verilmişse
     *         prompt'un EN BAŞINA eklenir. Destekleyen sürücüler (Gemini) kullanır, diğerleri yok sayar.
     * @return string  Giydirilmiş sonucun yerel mutlak dosya yolu
     */
    public function tryOn(string $modelImagePath, string $garmentImagePath, array $extraGarmentImages = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string;

    /**
     * Bu sürücünün o anki config'e göre çalıştırdığı modelin tanımlayıcısı
     * (ör. "fal-ai/fashn/tryon/v1.6", "gemini-3.1-flash-image", "mock").
     * Raporlarda hangi görselin hangi modelle üretildiğini göstermek için
     * TryonResult'a kaydedilir (bkz. ProductOnModelService::generate).
     */
    public function modelIdentifier(): string;
}
