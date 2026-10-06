<?php

namespace Modules\Creative\Services\Mockup;

/**
 * Mokap stilisti kural motoru (saf PHP: DB'siz, AI'sız, config'siz — bkz. GarmentIdentityRuleEngine).
 *
 * Stilistin (AI) sıraladığı seçenek ANAHTARLARINI doğrular, uyumsuzları eler, çeşitlilik kuralını
 * uygular ve kombin cümlesini seçeneklerin prompt_en alanlarından KURAR. AI'nın yazdığı serbest
 * metin hiçbir zaman görsel prompt'una girmez.
 *
 * Kurallar:
 *  - Seçenek bu ürün bölgesine, cinsiyete ve boya uygun olmalı (tags.regions / genders / lengths).
 *  - Alt ürün → üst parça zorunlu ve içeride; üst ürün → alt parça zorunlu; dış giyim → iç kombin (üst +
 *    alt) zorunlu, boy kısıtları dış katmana değil iç kombine bakar; takım / tek parça → yalnız
 *    ayakkabı, çorap, saç aksesuarı (göğüste ya da bilekte aksesuar yok). Üst ürünlerde göğüste aksesuar yok.
 *  - Midi/maksi boyda çorap görünmez → "none".
 *  - Renk yalnız renklenebilir (tags.colorable) tamamlayıcı parçaya uygulanır.
 *  - Çeşitlilik: aynı kategorinin son ürünlerinde kullanılan (tamamlayıcı parça + ayakkabı) ikilisi
 *    tekrarlanmaz; AI'nın sıradaki adaylarından, o da yetmezse listeden ilk uygun ikili seçilir.
 *  - Geçersiz / eksik seçim listedeki ilk uygun seçenekle doldurulur ve uyarı olarak kaydedilir.
 *  - Pozlar: bölgeye uygun (mockup_regions) ve gereksinimi (requires) ürün özelliklerince karşılanan pozlar.
 */
class MockupStylistRuleEngine
{
    public const LOW_CONFIDENCE = 0.5;

    /**
     * @param  array{picks?:array<string,array<int,string>>, poses?:array<int,string>, features?:array<int,string>, confidence?:float}  $ai
     * @param  array<string,array<string,array{label_tr:string,prompt_en:string,tags:array<string,mixed>}>>  $catalog  kind => key => seçenek
     * @param  array<string,array{regions:array<int,string>,requires:array<int,string>}>  $poses  kullanılabilir pozlar (ana kare hariç), sıralı
     * @param  array{region:string,length:string,gender:?string,extra_poses:int,recent:array<int,array{0:?string,1:?string}>}  $ctx
     * @return array{pieces:array<string,string>, sentence:string, poses:array<int,string>, warnings:array<int,string>, confidence:?float}
     */
    public function resolve(array $ai, array $catalog, array $poses, array $ctx): array
    {
        $region = $ctx['region'];
        $warnings = [];
        $picks = (array) ($ai['picks'] ?? []);

        $complement = match ($region) {
            'bottom', 'outerwear' => 'top',
            'top'    => 'bottom',
            default  => null,
        };

        // Tamamlayıcı parça + ayakkabı: çeşitlilik kuralıyla birlikte seçilir (dış giyimde tamamlayıcı = iç üst;
        // iç alt ayrıca seçilir).
        $compCands = $complement ? $this->candidates($complement, $picks, $catalog, $ctx) : [null];
        $footCands = $this->candidates('footwear', $picks, $catalog, $ctx);
        if ($complement && $compCands === []) {
            throw new \RuntimeException("Stilist seçenek listesinde bu ürüne uygun '{$complement}' yok.");
        }
        if ($footCands === []) {
            throw new \RuntimeException("Stilist seçenek listesinde bu ürüne uygun ayakkabı yok.");
        }
        [$comp, $foot] = $this->diverse($compCands, $footCands, (array) ($ctx['recent'] ?? []));
        if ([$comp, $foot] !== [$compCands[0], $footCands[0]]) {
            $warnings[] = 'Çeşitlilik: aynı kategoride son ürünlerde kullanılan kombin tekrarlanmasın diye ' . implode(' + ', array_filter([$comp, $foot])) . ' seçildi.';
        }

        $pieces = array_filter([$complement => $comp, 'footwear' => $foot]);
        foreach ([$complement, 'footwear'] as $kind) {
            if ($kind && ($picks[$kind][0] ?? null) !== null && ! $this->allows($kind, (string) $picks[$kind][0], $catalog, $ctx)) {
                $warnings[] = "Stilistin seçimi uygun değil ({$kind}: {$picks[$kind][0]}); listeden uygun seçenek kullanıldı.";
            }
        }

        if ($comp !== null && ! empty($catalog[$complement][$comp]['tags']['colorable'])) {
            $pieces['color'] = $this->pick('color', $picks, $catalog, $ctx, $warnings);
        }
        if ($region === 'outerwear') {
            if ($this->candidates('bottom', $picks, $catalog, $ctx) === []) {
                throw new \RuntimeException("Stilist seçenek listesinde bu ürüne uygun 'bottom' yok.");
            }
            $pieces['bottom'] = $this->pick('bottom', $picks, $catalog, $ctx, $warnings);
        }

        // Dış giyimde boy dış katmanındır; çorabı iç kombin belirler.
        $long = $region !== 'outerwear' && in_array($ctx['length'], ['midi', 'maxi'], true);
        $pieces['socks'] = $long ? 'none' : $this->pick('socks', $picks, $catalog, $ctx, $warnings);
        $pieces['accessory'] = $this->pick('accessory', $picks, $catalog, $ctx, $warnings);

        $confidence = isset($ai['confidence']) && is_numeric($ai['confidence']) ? max(0.0, min(1.0, (float) $ai['confidence'])) : null;
        if ($confidence !== null && $confidence < self::LOW_CONFIDENCE) {
            $warnings[] = sprintf('Stilist kararından emin değil (güven %.2f).', $confidence);
        }

        return [
            'pieces'     => $pieces,
            'sentence'   => $this->sentence($region, $pieces, $catalog),
            'poses'      => $this->poses((array) ($ai['poses'] ?? []), $poses, $region, (array) ($ai['features'] ?? []), (int) $ctx['extra_poses']),
            'warnings'   => $warnings,
            'confidence' => $confidence,
        ];
    }

    /** Bir türün geçerli adayları: önce AI'nın sırası, sonra liste sırası (tekrarsız). @return array<int,string> */
    private function candidates(string $kind, array $picks, array $catalog, array $ctx): array
    {
        $keys = [...array_map('strval', (array) ($picks[$kind] ?? [])), ...array_keys($catalog[$kind] ?? [])];

        return array_values(array_unique(array_filter($keys, fn ($k) => $this->allows($kind, $k, $catalog, $ctx))));
    }

    private function pick(string $kind, array $picks, array $catalog, array $ctx, array &$warnings): string
    {
        $cands = $this->candidates($kind, $picks, $catalog, $ctx);
        $first = $picks[$kind][0] ?? null;
        if ($first !== null && ($cands[0] ?? null) !== (string) $first) {
            $warnings[] = "Stilistin seçimi uygun değil ({$kind}: {$first}); listeden uygun seçenek kullanıldı.";
        }

        return $cands[0] ?? 'none';
    }

    /** Seçenek bu ürün bağlamında (bölge, cinsiyet, boy, aksesuar yeri) kullanılabilir mi? */
    public function allows(string $kind, string $key, array $catalog, array $ctx): bool
    {
        $opt = $catalog[$kind][$key] ?? null;
        if ($opt === null) {
            return false;
        }
        $t = (array) ($opt['tags'] ?? []);
        $in = fn (string $tag, ?string $value) => empty($t[$tag]) || $value === null || in_array($value, (array) $t[$tag], true);

        $length = $ctx['region'] === 'outerwear' ? null : ($ctx['length'] ?: null);
        if (! $in('regions', $ctx['region']) || ! $in('genders', $ctx['gender'] ?? null) || ! $in('lengths', $length)) {
            return false;
        }
        if ($kind === 'accessory' && isset($t['placement'])) {
            $allowed = match ($ctx['region']) {
                'bottom' => ['hair', 'wrist', 'chest'],
                'top', 'outerwear' => ['hair', 'wrist'],
                default  => ['hair'],
            };

            return in_array($t['placement'], $allowed, true);
        }

        return true;
    }

    /**
     * Son ürünlerde kullanılmamış ilk (tamamlayıcı, ayakkabı) ikilisi; hepsi kullanılmışsa ilk ikili.
     *
     * @param  array<int,?string>  $comps
     * @param  array<int,string>  $foots
     * @param  array<int,array{0:?string,1:?string}>  $recent
     * @return array{0:?string,1:string}
     */
    private function diverse(array $comps, array $foots, array $recent): array
    {
        $used = array_map(fn ($r) => ($r[0] ?? '') . '|' . ($r[1] ?? ''), $recent);
        foreach ($comps as $c) {
            foreach ($foots as $f) {
                if (! in_array(($c ?? '') . '|' . $f, $used, true)) {
                    return [$c, $f];
                }
            }
        }

        return [$comps[0], $foots[0]];
    }

    /** Kombin cümlesi yalnız seçeneklerin prompt_en metinlerinden kurulur. */
    private function sentence(string $region, array $pieces, array $catalog): string
    {
        $text = fn (string $kind) => trim((string) ($catalog[$kind][$pieces[$kind] ?? '']['prompt_en'] ?? ''));

        $garments = [];
        foreach (['top', 'bottom'] as $kind) {
            if (isset($pieces[$kind]) && $text($kind) !== '') {
                // Renk tamamlayıcı parçaya (alt üründe/dış giyimde üst, üst üründe alt) uygulanır.
                $colored = $kind === ($region === 'top' ? 'bottom' : 'top') && isset($pieces['color']) && $text('color') !== '';
                $garments[] = $text($kind) . ($colored ? ' in ' . $text('color') : '') . ($kind === 'top' && $region !== 'top' ? ', fully tucked in' : '');
            }
        }
        $main = $garments === [] ? null : implode(', ', $garments);

        $rest = array_values(array_filter([$text('footwear'), $text('socks'), $text('accessory')]));
        $list = count($rest) > 1 ? implode(', ', array_slice($rest, 0, -1)) . ' and ' . end($rest) : ($rest[0] ?? '');

        return ucfirst($main !== null ? trim($main . ($list !== '' ? ', with ' . $list : '')) : $list) . '.';
    }

    /** @return array<int,string> */
    private function poses(array $aiPoses, array $poses, string $region, array $features, int $extra): array
    {
        $ok = array_keys(array_filter($poses, fn ($p) => in_array($region, (array) ($p['regions'] ?? []), true)
            && array_diff((array) ($p['requires'] ?? []), $features) === []));
        $chosen = array_values(array_unique(array_filter(array_map('strval', $aiPoses), fn ($k) => in_array($k, $ok, true))));
        foreach ($ok as $k) {
            if (count($chosen) >= $extra) {
                break;
            }
            if (! in_array($k, $chosen, true)) {
                $chosen[] = $k;
            }
        }

        return array_slice($chosen, 0, $extra);
    }
}
