<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\CopyRequest;
use Modules\Creative\Services\Ai\Support\CopySlots;

/**
 * On-image pazarlama metni istemini kurar. Saf/yan-etkisiz (test edilebilir):
 * ürün bağlamı + markanın copy kimliği (brief/tone/cta_phrases/banned_words) +
 * istenen slotlar + rol yönergeleri tek bir Gemini metin istemine çevrilir.
 *
 * Marka kimliği taban çizgisidir: extraInstructions (ret sonrası düzeltme) brief'i
 * EZMEZ, üstüne EKLENİR.
 */
class CopyPromptBuilder
{
    /** @var array<string,string>  rol => model için Türkçe yönerge */
    private const ROLE_GUIDANCE = [
        'headline'    => 'kısa, çarpıcı ana başlık (en fazla 5 kelime)',
        'subheadline' => 'başlığı destekleyen kısa alt başlık / slogan (en fazla 10 kelime)',
        'cta'         => 'harekete geçirici kısa ifade (en fazla 4 kelime)',
        'body'        => 'kısa açıklama cümlesi (en fazla 20 kelime)',
    ];

    public function build(CopyRequest $request): string
    {
        $context = $this->contextBlock($request);
        $slots   = $this->slotBlock($request->slotKeys);
        $rules   = $this->rulesBlock($request);
        $shape   = $this->jsonShape($request->slotKeys);

        return <<<PROMPT
        Sen bir moda/e-ticaret markasının kreatif metin yazarısın. Aşağıdaki ürün için,
        bir sosyal medya görselinin üzerine yerleştirilecek KISA pazarlama metinleri üret.

        Ürün ve marka bağlamı:
        {$context}

        Doldurulacak metin alanları (her biri için ayrı, birbirini tamamlayan metin üret):
        {$slots}

        Kurallar:
        {$rules}

        SADECE şu JSON şemasında yanıt ver, başka hiçbir metin/açıklama ekleme:
        {$shape}
        PROMPT;
    }

    private function contextBlock(CopyRequest $request): string
    {
        $lines = ['- Ürün: ' . $request->productName];

        if ($request->category) {
            $lines[] = '- Kategori: ' . $request->category;
        }

        foreach ($request->attributes as $key => $value) {
            $lines[] = '- ' . ucfirst((string) $key) . ': ' . $value;
        }

        if ($request->brief !== '') {
            $lines[] = '- Marka brief: ' . $request->brief;
        }

        if ($request->tone !== '') {
            $lines[] = '- Ton: ' . $request->tone;
        }

        if ($request->aspectLabel) {
            $lines[] = '- Format: ' . $request->aspectLabel . ' (dar alan; metinleri kısa tut)';
        }

        if ($request->extraInstructions !== null && trim($request->extraInstructions) !== '') {
            // Marka brief'inin YERİNE değil, ÜZERİNE ek talimat.
            $lines[] = '- Ek düzeltme talimatı (brief korunur, buna ek): ' . trim($request->extraInstructions);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int,string>  $slotKeys
     */
    private function slotBlock(array $slotKeys): string
    {
        $lines = [];
        foreach ($slotKeys as $key) {
            $role     = CopySlots::roleFor($key) ?? 'body';
            $guidance = self::ROLE_GUIDANCE[$role] ?? self::ROLE_GUIDANCE['body'];
            $lines[]  = "- \"{$key}\": {$guidance}";
        }

        return implode("\n", $lines);
    }

    private function rulesBlock(CopyRequest $request): string
    {
        $rules = [
            '- Dil: Türkçe, akıcı ve marka tonuna uygun.',
            '- Görsel üstü metin: kısa ve vurucu olmalı; cümleleri gereksiz uzatma.',
        ];

        if ($request->ctaPhrases !== []) {
            $rules[] = '- CTA alan(lar)ı için tercihen şu ifadelerden birini kullan: '
                . implode(', ', $request->ctaPhrases) . '.';
        }

        if ($request->bannedWords !== []) {
            $rules[] = '- ŞU kelimeleri ASLA kullanma: ' . implode(', ', $request->bannedWords) . '.';
        }

        return implode("\n", $rules);
    }

    /**
     * @param  array<int,string>  $slotKeys
     */
    private function jsonShape(array $slotKeys): string
    {
        $parts = [];
        foreach ($slotKeys as $key) {
            $parts[] = json_encode($key, JSON_UNESCAPED_UNICODE) . ': "..."';
        }

        return '{' . implode(', ', $parts) . '}';
    }
}
