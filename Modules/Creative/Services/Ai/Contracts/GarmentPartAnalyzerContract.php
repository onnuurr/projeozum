<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Bir giysi parçasının (kırpılmış görsel) yapılandırılmış özellik analizini
 * üretir — renk/desen/doku/kumaş/dikiş/donanım tipi + her alan için confidence.
 * Sonuç VERSIONED bir zarfla saklanır (analysis_version/model/prompt_version/
 * generated_at/data) — prompt metni ileride değişirse hangi analizlerin
 * "eski" (stale) olduğu anlaşılabilsin diye (bkz. ROADMAP.md Faz G.5).
 *
 * Bu sonuç ASLA doğrudan try-on prompt'una yazılmaz — aradaki
 * {@see \Modules\Creative\Services\GarmentIdentityRuleEngine} confidence
 * eşiğinin altındaki alanları eler ve nihai önceliği hesaplar. Sürücü
 * herhangi bir hata durumunda (API hatası, geçersiz JSON) tüm alanları
 * confidence=0 ile döner — asla istisna fırlatmaz, giydirme pipeline'ı bu
 * adım yüzünden bozulmaz (mevcut graceful-degrade felsefesi).
 */
interface GarmentPartAnalyzerContract
{
    /**
     * @return array{
     *   analysis_version:int,
     *   model:string,
     *   prompt_version:int,
     *   generated_at:string,
     *   data:array{
     *     instance_priority:array{value:?string,confidence:float},
     *     color:array{value:?string,raw_text:?string,confidence:float},
     *     pattern:array{value:?string,raw_text:?string,confidence:float},
     *     texture:array{value:?string,raw_text:?string,confidence:float},
     *     fabric:array{value:?string,raw_text:?string,confidence:float},
     *     stitching:array{value:?string,raw_text:?string,confidence:float},
     *     hardware_type:array{value:?string,raw_text:?string,confidence:float},
     *     notes:?string
     *   }
     * }
     */
    public function analyze(string $cropImagePath, string $labelKey, string $labelDisplay, string $preservationCategory): array;
}
