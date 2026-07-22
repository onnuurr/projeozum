<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\CopyRequest;

/**
 * Marka kriterlerine göre görsel-üstü pazarlama metni (headline/sub-headline/CTA)
 * üreten sürücü sözleşmesi. Sonuç VERSIONED bir zarfla döner
 * (copy_version/model/prompt_version/generated_at/data) — aynı disiplin
 * {@see GarmentPartAnalyzerContract} ile.
 *
 * Bu sonuç ASLA doğrudan render'a yazılmaz — aradaki
 * {@see \Modules\Creative\Services\CreativeCopyRuleEngine} confidence eşiğinin
 * altındaki alanları eler, CTA'yı kapalı listeye göre normalize/coerce eder ve
 * yasaklı kelime taraması yapar. Sürücü herhangi bir hata durumunda (API
 * hatası, geçersiz JSON) tüm alanları confidence=0 ile döner — asla istisna
 * fırlatmaz, üretim pipeline'ı bu adım yüzünden bozulmaz.
 */
interface CopyGeneratorContract
{
    /**
     * @return array{
     *   copy_version:int,
     *   model:string,
     *   prompt_version:int,
     *   generated_at:string,
     *   data:array{
     *     headline:array{value:?string,confidence:float},
     *     sub_headline:array{value:?string,confidence:float},
     *     cta:array{value:?string,confidence:float}
     *   }
     * }
     */
    public function generate(CopyRequest $request): array;
}
