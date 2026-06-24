<?php

namespace Modules\Atelier\Services\Concept\Contracts;

use Modules\Atelier\Services\Concept\ConceptRequest;

/**
 * Model-agnostik konsept görsel üreticisi.
 *
 * Soyutlama sabit kalır; arkasındaki sağlayıcı (Gemini, mock, ileride başka
 * model) config ile değiştirilir. Pazar 12 ayda döndüğü için tek modele
 * kilitlenmemek esastır (yol haritası §3).
 */
interface ConceptImageGeneratorContract
{
    /**
     * Tarife göre bir veya daha çok konsept görseli üretir ve kalıcı diske yazar.
     *
     * @return array<int,string>  Saklanan görsellerin diske göreli yolları.
     */
    public function generate(ConceptRequest $request): array;
}
