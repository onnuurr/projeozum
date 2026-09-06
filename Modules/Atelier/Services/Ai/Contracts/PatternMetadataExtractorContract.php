<?php

namespace Modules\Atelier\Services\Ai\Contracts;

/**
 * Kalıp PDF'inin kapak sayfası görselinden ürün tipi + beden aralığını tahmin
 * eder (PİLOT — bkz. config/atelier.php pattern_metadata_ai). Bilinmeyen
 * satıcı formatlarında (profil eşleşmedi) devreye girer; ruslan/nipnaps gibi
 * profillerin lexicon-tabanlı ayrıştırmasının YERİNE değil, YOKLUĞUNDA çalışır.
 *
 * Sonuç VERSIONED bir zarfla döner (analysis_version/model/prompt_version/
 * generated_at/data) — Creative modülünün {@see \Modules\Creative\Services\Ai\
 * Contracts\GarmentPartAnalyzerContract} ile AYNI desen. Bu sonuç ASLA doğrudan
 * patterns.product_type/size_range kolonlarına yazılmaz; aradaki
 * {@see \Modules\Atelier\Services\PatternMetadataRuleEngine} confidence
 * eşiğinin altındaki alanları eler. Sürücü herhangi bir hata durumunda (API
 * hatası, geçersiz JSON) tüm alanları confidence=0 ile döner — asla istisna
 * fırlatmaz, çıkarım pipeline'ı bu adım yüzünden bozulmaz.
 */
interface PatternMetadataExtractorContract
{
    /**
     * @param  string  $coverImagePath  PDF'in ilk sayfasının PNG render'ının yerel yolu
     * @return array{
     *   analysis_version:int,
     *   model:string,
     *   prompt_version:int,
     *   generated_at:string,
     *   data:array{
     *     product_type:array{value:?string,raw_text:?string,confidence:float},
     *     size_range:array{value:?string,confidence:float}
     *   }
     * }
     */
    public function extract(string $coverImagePath): array;
}
