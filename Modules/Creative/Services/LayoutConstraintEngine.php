<?php

namespace Modules\Creative\Services;

/**
 * AI-kompozisyon (fal.ai Flux, ai_compose motoru) çıktısının profesyonel
 * kompozisyon kurallarına uyup uymadığını denetleyen SAF iş kuralı katmanı.
 * Hiçbir AI/OCR/DB çağrısı YAPMAZ — aynı disiplin {@see CreativeCopyRuleEngine}/
 * {@see GarmentIdentityRuleEngine} ile. OCR sonucunu kendisi üretmez; orkestratör
 * ({@see CreativeRenderService}) {@see \Modules\Creative\Services\Vision\TextRecognizerContract}
 * çıktısını buraya verir (bkz. ROADMAP.md Faz J).
 *
 * "Öznel brand-similarity skoru DEĞİL, somut/ölçülebilir kontrol" ilkesi
 * (ROADMAP.md Faz H) burada uygulanır: kelime sayısı, OCR metin eşleşmesi,
 * safe-margin içinde kalma gibi ikili/ölçülebilir kontroller.
 */
class LayoutConstraintEngine
{
    /**
     * Render'dan ÖNCE, ucuz/I-O'suz doğrulama — boşuna bir AI çağrısı
     * yapılmadan headline/CTA metninin uzunluk kısıtlarını aşıp aşmadığını
     * kontrol eder.
     *
     * @param  array{headline?:?string,sub_headline?:?string,cta_button?:?string}  $copyValues
     * @return array{passed:bool,violations:array<int,string>}
     */
    public function checkIntendedText(array $copyValues): array
    {
        $violations = [];

        $headline = trim((string) ($copyValues['headline'] ?? ''));
        if ($headline !== '') {
            $maxWords = (int) config('creative.composition.constraints.headline_max_words', 3);
            $maxLines = (int) config('creative.composition.constraints.headline_max_lines', 1);

            $wordCount = count(preg_split('/\s+/', $headline, -1, PREG_SPLIT_NO_EMPTY));
            if ($wordCount > $maxWords) {
                $violations[] = sprintf('headline kelime sayısı %d, izin verilen azami %d', $wordCount, $maxWords);
            }

            $lineCount = count(explode("\n", $headline));
            if ($lineCount > $maxLines) {
                $violations[] = sprintf('headline satır sayısı %d, izin verilen azami %d', $lineCount, $maxLines);
            }
        }

        $cta = trim((string) ($copyValues['cta_button'] ?? ''));
        if ($cta !== '') {
            $maxCtaWords = (int) config('creative.composition.constraints.cta_max_words', 3);
            $ctaWordCount = count(preg_split('/\s+/', $cta, -1, PREG_SPLIT_NO_EMPTY));
            if ($ctaWordCount > $maxCtaWords) {
                $violations[] = sprintf('CTA kelime sayısı %d, izin verilen azami %d', $ctaWordCount, $maxCtaWords);
            }
        }

        return ['passed' => $violations === [], 'violations' => $violations];
    }

    /**
     * Üretilmiş kompozisyonun OCR sonucunu istenen metinle ve safe-margin
     * kurallarıyla karşılaştırır.
     *
     * @param  array<int,array{text:string,x:int,y:int,w:int,h:int,confidence:float}>  $ocrWords
     * @param  array{headline?:?string,sub_headline?:?string,cta_button?:?string}  $intendedText
     * @param  array{width?:?int,height?:?int}  $format
     * @return array{passed:bool,violations:array<int,string>}
     */
    public function evaluate(array $ocrWords, array $intendedText, array $format, bool $wantCta): array
    {
        $violations = [];
        $ocrText    = $this->joinWords($ocrWords);

        $headline = trim((string) ($intendedText['headline'] ?? ''));
        if ($headline !== '') {
            $match = $this->bestMatch($headline, $ocrWords);
            if ($match === null || $match['similarity'] < $this->minSimilarity()) {
                $violations[] = sprintf(
                    'headline OCR uyuşmazlığı ("%s" bulunamadı, benzerlik %.2f < %.2f)',
                    $headline,
                    $match['similarity'] ?? 0.0,
                    $this->minSimilarity(),
                );
            } elseif (! $this->withinSafeMargin($match['box'], $format)) {
                $violations[] = 'headline metni safe-margin dışında konumlanmış';
            }
        }

        if ($wantCta) {
            $cta = trim((string) ($intendedText['cta_button'] ?? ''));
            if ($cta === '') {
                $violations[] = 'CTA metni zorunlu ama üretilmedi';
            } else {
                $match = $this->bestMatch($cta, $ocrWords);
                if ($match === null || $match['similarity'] < $this->minSimilarity()) {
                    $violations[] = sprintf(
                        'CTA OCR uyuşmazlığı ("%s" bulunamadı, benzerlik %.2f < %.2f)',
                        $cta,
                        $match['similarity'] ?? 0.0,
                        $this->minSimilarity(),
                    );
                } elseif (! $this->withinSafeMargin($match['box'], $format)) {
                    $violations[] = 'CTA metni safe-margin dışında konumlanmış';
                }
            }
        }

        if ($ocrWords === []) {
            $violations[] = 'OCR hiçbir metin bulamadı (üretim/tanıma hatası olabilir)';
        }

        unset($ocrText);

        return ['passed' => $violations === [], 'violations' => array_values(array_unique($violations))];
    }

    /**
     * Render'a hiç gerek duymadan, salt slot GEOMETRİSİNDEN (CreativeTemplate.slots)
     * hesaplanan kompozisyon kontrolleri — hem manuel yüklenen hem
     * TemplateGeneratorService çıktısı şablonlar için aynı şekilde çalışır
     * (bkz. ROADMAP.md Faz N). "Öznel skor DEĞİL, ölçülebilir kontrol" ilkesi
     * burada da geçerli: sonuç geçti/kaldı + sebep, uydurulmuş bir sayı değil.
     *
     * @param  array<int,array<string,mixed>>  $slots
     * @return array{passed:bool,violations:array<int,string>,metrics:array<string,float>}
     */
    public function checkSlotGeometry(array $slots, int $width, int $height): array
    {
        $violations = [];
        $canvasArea = max(1, $width * $height);

        // Tuvalin büyük bir kısmını kaplayan görsel slotu (tam-kapak fotoğraf/arka
        // plan) kasıtlı bir "zemin" katmanıdır — üstüne oturan metin/logo ile
        // "çakışması" ya da işgal ettiği alanın "boşluk" sayılmaması beklenir.
        $backgroundRatio = 0.6;

        $boxes = [];
        $keyCounts = [];
        foreach ($slots as $i => $slot) {
            $key = (string) ($slot['key'] ?? $i);
            $keyCounts[$key] = ($keyCounts[$key] ?? 0) + 1;
            $box = $this->estimateBox($slot);
            $isBackground = ($slot['type'] ?? null) === 'image' && ($box['w'] * $box['h']) >= ($canvasArea * $backgroundRatio);
            // Çakışma kutusu, boşluk hesabındaki kutudan bilerek DAHA DAR: `data-w`
            // metnin izin verilen AZAMİ genişliğidir (gerçek render genişliği değil),
            // olduğu gibi kullanmak komşu bir kolonun geniş max-width'inin üzerine
            // "sızmasını" yanlışlıkla çakışma sayar. Çakışma kontrolü yalnız
            // belirgin/kesin çakışmaları (aynı konuma yerleştirilmiş slotlar) yakalasın
            // diye metin genişliği burada küçük tutuluyor.
            $overlapBox = $box;
            if (($slot['type'] ?? null) === 'text') {
                $overlapBox['w'] = min($box['w'], max(1.0, (float) ($slot['font_size'] ?? 16)) * 4);
            }
            $boxes[] = ['key' => $key, 'box' => $box, 'overlapBox' => $overlapBox, 'background' => $isBackground];
        }

        foreach ($keyCounts as $key => $count) {
            if ($count > 1) {
                $violations[] = sprintf('"%s" anahtarı %d kez tekrarlanmış (yinelenen slot)', $key, $count);
            }
        }

        $heroSlot = collect($slots)->firstWhere('key', 'product_image');
        $heroCoveragePct = 0.0;
        if ($heroSlot) {
            $heroArea = max(0.0, (float) ($heroSlot['w'] ?? 0)) * max(0.0, (float) ($heroSlot['h'] ?? 0));
            $heroCoveragePct = $heroArea / $canvasArea;
            $minHero = (float) config('creative.constraints.layout.hero_coverage_min_pct', 0.30);
            if ($heroCoveragePct < $minHero) {
                $violations[] = sprintf(
                    'ürün görseli tuvalin yalnız %%%.1f\'ini kaplıyor (asgari %%%.1f)',
                    $heroCoveragePct * 100,
                    $minHero * 100,
                );
            }
        }

        $occupiedArea = array_sum(array_map(
            fn (array $b) => $b['background'] ? 0.0 : $b['box']['w'] * $b['box']['h'],
            $boxes,
        ));
        $whitespacePct = max(0.0, 1 - ($occupiedArea / $canvasArea));
        $minWhitespace = (float) config('creative.constraints.layout.whitespace_min_pct', 0.08);
        if ($whitespacePct < $minWhitespace) {
            $violations[] = sprintf(
                'boşluk oranı %%%.1f (asgari %%%.1f)',
                $whitespacePct * 100,
                $minWhitespace * 100,
            );
        }

        $tolerance = (int) config('creative.constraints.layout.overlap_tolerance_px', 4);
        for ($a = 0; $a < count($boxes); $a++) {
            if ($boxes[$a]['background']) {
                continue;
            }
            for ($b = $a + 1; $b < count($boxes); $b++) {
                if ($boxes[$b]['background']) {
                    continue;
                }
                if ($this->overlapArea($boxes[$a]['overlapBox'], $boxes[$b]['overlapBox']) > ($tolerance * $tolerance)) {
                    $violations[] = sprintf('"%s" ve "%s" slotları çakışıyor', $boxes[$a]['key'], $boxes[$b]['key']);
                }
            }
        }

        if ((bool) config('creative.constraints.layout.require_logo_slot', false)) {
            $hasLogo = collect($slots)->contains(fn ($s) => ($s['key'] ?? null) === 'logo');
            if (! $hasLogo) {
                $violations[] = "'logo' anahtarlı bir slot tanımlanmamış";
            }
        }

        return [
            'passed'     => $violations === [],
            'violations' => array_values(array_unique($violations)),
            'metrics'    => [
                'hero_coverage_pct' => round($heroCoveragePct * 100, 1),
                'whitespace_pct'    => round($whitespacePct * 100, 1),
            ],
        ];
    }

    /**
     * Görsel slotu gerçek w×h; metin slotu data-w (varsa) × font_size, yoksa
     * font_size × 8 kaba glyph-genişliği tahminiyle yaklaşık kutu.
     *
     * @param  array<string,mixed>  $slot
     * @return array{x:float,y:float,w:float,h:float}
     */
    private function estimateBox(array $slot): array
    {
        $x = (float) ($slot['x'] ?? 0);
        $y = (float) ($slot['y'] ?? 0);

        if (($slot['type'] ?? null) === 'text') {
            $fontSize = max(1.0, (float) ($slot['font_size'] ?? 16));
            $w = (float) ($slot['w'] ?? 0) ?: $fontSize * 8;
            // Baseline'ın üstündeki gerçek "ink" yüksekliği kabaca cap-height'tır
            // (~%75-85 font-size) — tam satır yüksekliği (leading dahil, ~1.3x)
            // kullanmak komşu elemanlarla olan normal/kasıtlı boşlukları
            // "çakışma" olarak yanlış işaretler (bkz. ROADMAP.md Faz N testleri).
            $h = $fontSize * 0.85;

            // Metin dikey konumu render'da baseline'dır (y = alt kenar); kutuyu yukarı aç.
            return ['x' => $x, 'y' => max(0.0, $y - $h), 'w' => $w, 'h' => $h];
        }

        return ['x' => $x, 'y' => $y, 'w' => (float) ($slot['w'] ?? 0), 'h' => (float) ($slot['h'] ?? 0)];
    }

    /**
     * @param  array{x:float,y:float,w:float,h:float}  $a
     * @param  array{x:float,y:float,w:float,h:float}  $b
     */
    private function overlapArea(array $a, array $b): float
    {
        $ix = max(0.0, min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']));
        $iy = max(0.0, min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']));

        return $ix * $iy;
    }

    private function minSimilarity(): float
    {
        return (float) config('creative.composition.constraints.text_match_min_similarity', 0.85);
    }

    /**
     * OCR kelimelerini ardışık pencereler halinde birleştirip intended metne
     * en çok benzeyen pencereyi bulur (çok kelimeli başlıklar birden çok OCR
     * kutusuna bölünmüş olabilir).
     *
     * @param  array<int,array{text:string,x:int,y:int,w:int,h:int,confidence:float}>  $ocrWords
     * @return array{similarity:float,box:array{x:int,y:int,w:int,h:int}}|null
     */
    private function bestMatch(string $intended, array $ocrWords): ?array
    {
        if ($ocrWords === []) {
            return null;
        }

        $needleWordCount = max(1, count(preg_split('/\s+/', trim($intended), -1, PREG_SPLIT_NO_EMPTY)));
        $best = null;

        for ($start = 0; $start < count($ocrWords); $start++) {
            $window = array_slice($ocrWords, $start, $needleWordCount);
            $candidateText = implode(' ', array_map(fn ($w) => $w['text'], $window));

            similar_text(mb_strtolower($intended), mb_strtolower($candidateText), $pct);
            $similarity = $pct / 100.0;

            if ($best === null || $similarity > $best['similarity']) {
                $best = ['similarity' => $similarity, 'box' => $this->unionBox($window)];
            }
        }

        return $best;
    }

    /**
     * @param  array<int,array{x:int,y:int,w:int,h:int}>  $words
     * @return array{x:int,y:int,w:int,h:int}
     */
    private function unionBox(array $words): array
    {
        $x1 = min(array_column($words, 'x'));
        $y1 = min(array_column($words, 'y'));
        $x2 = max(array_map(fn ($w) => $w['x'] + $w['w'], $words));
        $y2 = max(array_map(fn ($w) => $w['y'] + $w['h'], $words));

        return ['x' => $x1, 'y' => $y1, 'w' => $x2 - $x1, 'h' => $y2 - $y1];
    }

    /**
     * @param  array{x:int,y:int,w:int,h:int}  $box
     * @param  array{width?:?int,height?:?int}  $format
     */
    private function withinSafeMargin(array $box, array $format): bool
    {
        $width  = (int) ($format['width'] ?? 0);
        $height = (int) ($format['height'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            // Format bilgisi yoksa geometrik kontrol yapılamaz — sadece metin
            // eşleşmesine güvenilir (bilinçli, ölçülemez olanı reddetmeyen tercih).
            return true;
        }

        $marginPct = (float) config('creative.composition.constraints.safe_margin_pct', 0.06);
        $marginX   = $width * $marginPct;
        $marginY   = $height * $marginPct;

        return $box['x'] >= $marginX
            && $box['y'] >= $marginY
            && ($box['x'] + $box['w']) <= ($width - $marginX)
            && ($box['y'] + $box['h']) <= ($height - $marginY);
    }

    /**
     * @param  array<int,array{text:string}>  $ocrWords
     */
    private function joinWords(array $ocrWords): string
    {
        return implode(' ', array_map(fn ($w) => $w['text'], $ocrWords));
    }
}
