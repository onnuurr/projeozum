<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Try-on prompt üreten sınıfların ortak sözleşmesi.
 *
 * Tek amacı: {@see \Modules\Creative\Console\Commands\TryonPromptCompareCommand}'ın
 * iki farklı prompt SÜRÜMÜNÜ (ör. mevcut {@see \Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder}
 * ile üzerinde denenen bir varyantını) aynı sabit test setine karşı çalıştırıp
 * karşılaştırabilmesi — üretim kodunda bu arayüz üzerinden tip belirtmek ZORUNLU
 * değildir, {@see \Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOn} hâlâ
 * somut sınıfı enjekte eder.
 */
interface TryOnPromptBuilderContract
{
    /**
     * @param  array<int,array{path?:string,label:?string,statement?:?string,priority?:?string,protect?:bool}>  $extras
     */
    public function build(array $extras = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string;
}
