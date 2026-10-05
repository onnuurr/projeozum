<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Mokap giydirmenin AI adımları: stilist, baz görseller (ana kare + türetilen poz), giydirme.
 * Üretim sürücüsü: GeminiMockupGenerator; anahtar yoksa MockMockupGenerator.
 */
interface MockupGeneratorContract
{
    /**
     * Ürün fotoğrafına bakıp kombin + poz önerisi döndürür (doğrulanmamış ham öneri —
     * MockupStylistService doğrular).
     *
     * @param  array{region:string,length:string,age:?string,gender:?string,poses:array<string,string>,extra_poses:int}  $context
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

    public function modelIdentifier(): string;
}
