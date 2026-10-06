<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Mokap giydirmenin AI adımları: stilist, baz görseller (ana kare + türetilen poz), giydirme.
 * Üretim sürücüsü: GeminiMockupGenerator; anahtar yoksa MockMockupGenerator.
 */
interface MockupGeneratorContract
{
    /**
     * Ürün fotoğrafına bakıp seçenek listelerinden sıralı anahtarlar + poz önerisi döndürür (doğrulanmamış
     * ham öneri — MockupStylistRuleEngine doğrular): {product_summary, picks:{kind:[key...]}, poses, features,
     * length, confidence, reasoning_tr}.
     *
     * @param  array{region:string,length:string,age:?string,gender:?string,poses:array<string,string>,extra_poses:int,options:array<string,array<string,string>>}  $context
     * @return array<string,mixed>
     */
    public function stylist(string $productImagePath, array $context): array;

    /**
     * Manken kimliği + poz şablonu + kombin → ana kare (geçici dosya yolu).
     *
     * @param  array{sentence:string,summary:string,region:string,length:string}  $outfit
     */
    public function masterBase(string $mannequinReferencePath, string $poseTemplatePath, array $outfit): string;

    /**
     * Ana kare + poz şablonu → aynı kombinle yeni poz (geçici dosya yolu).
     *
     * @param  array{sentence:string,summary:string,region:string,length:string}  $outfit
     */
    public function derivedBase(string $masterPath, string $poseTemplatePath, array $outfit): string;

    /**
     * Kırpılmış baz bölgesine ürünü giydirir (geçici dosya yolu).
     *
     * @param  array<int,string>  $productImagePaths  Ana ürün fotoğrafı + varsa ek görseller
     */
    public function tryOn(string $cropPath, array $productImagePaths, string $region, string $length, ?string $correction = null): string;

    /**
     * Faz 2 baz kontrolü: yan yana görsel (SOL ana kare, SAĞ türetilen poz) aynı çekim mi?
     *
     * @return array{verdict:string, issues:array<int,array{aspect?:string,detail_tr:string}>}
     */
    public function checkBaseConsistency(string $pairPath): array;

    /**
     * Faz 2 sadakat kontrolü: yan yana görsel (SOL ürün fotoğrafı, SAĞ giydirme sonucu).
     *
     * @param  string  $view  front|side|back|three_quarter — görünmeyen detaylar eksik sayılmasın
     * @return array{verdict:string, issues:array<int,array{severity?:string,detail_tr:string,fix_en?:string}>}
     */
    public function checkFidelity(string $pairPath, string $region, string $view): array;

    public function modelIdentifier(): string;
}
