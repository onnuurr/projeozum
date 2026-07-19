<?php

namespace Modules\Creative\Services;

use Modules\Creative\Models\GarmentLabel;

/**
 * Confidence eşiği altındaki analiz alanlarını eleyen, nihai önceliği (etiket
 * taban değeri ⊔ Gemini'nin örnek-bazlı değerlendirmesi) hesaplayan ve
 * bunlardan insan-okunur try-on prompt direktifleri üreten SAF iş kuralı
 * katmanı. Hiçbir AI çağrısı YAPMAZ, hiçbir DB sorgusu YAPMAZ — girdi
 * tamamen {@see GarmentScanService::cropsForTryOn()}'un ürettiği
 * zenginleştirilmiş extras dizisidir. Ham `analysis` JSON'u ASLA doğrudan
 * prompt'a yazılmaz; her zaman bu katmandan geçer (bkz. ROADMAP.md Faz G.5).
 */
class GarmentIdentityRuleEngine
{
    /**
     * @param  array<int,array{path:string,label:?string,label_key?:?string,default_priority?:?string,analysis?:?array}>  $extras
     * @return array<int,array{path:string,label:?string,statement:?string,priority:?string,protect:bool}>
     */
    public function directivesFor(array $extras): array
    {
        $threshold = (float) config('creative.garment_detection.analysis.confidence_threshold', 0.6);

        return array_map(fn (array $extra) => $this->directiveFor($extra, $threshold), $extras);
    }

    /**
     * @param  array<int,array{label:?string,priority:?string,protect:bool}>  $directives
     */
    public function protectListSentence(array $directives): ?string
    {
        $names = collect($directives)
            ->filter(fn (array $d) => $d['protect'] && $d['label'])
            ->pluck('label')
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        return 'Critical elements that must NOT be redesigned, replaced, or reinvented — preserve each '
            . 'exactly as shown in its reference image: ' . $names->implode(', ') . '.';
    }

    /**
     * @param  array{path:string,label:?string,label_key?:?string,default_priority?:?string,analysis?:?array}  $extra
     * @return array{path:string,label:?string,statement:?string,priority:?string,protect:bool}
     */
    private function directiveFor(array $extra, float $threshold): array
    {
        $labelKey = $extra['label_key'] ?? null;
        if (! $labelKey) {
            // Eski usul manuel yükleme (GarmentScan'e bağlı değil, label_key yok) —
            // davranış G.1-G.3'teki gibi AYNEN korunur, hiçbir yönerge eklenmez.
            return [
                'path' => $extra['path'], 'label' => $extra['label'] ?? null,
                'statement' => null, 'priority' => null, 'protect' => false,
            ];
        }

        $defaultPriority = $extra['default_priority'] ?? GarmentLabel::PRIORITY_MEDIUM;
        $data = is_array($extra['analysis'] ?? null) ? ($extra['analysis']['data'] ?? null) : null;

        $instancePriority = null;
        if (is_array($data) && (float) ($data['instance_priority']['confidence'] ?? 0) >= $threshold) {
            $instancePriority = $data['instance_priority']['value'] ?? null;
        }

        $priority = $this->maxPriority($defaultPriority, $instancePriority);
        $protect  = in_array($priority, [GarmentLabel::PRIORITY_CRITICAL, GarmentLabel::PRIORITY_HIGH], true);

        return [
            'path'      => $extra['path'],
            'label'     => $extra['label'] ?? null,
            'statement' => $this->buildStatement($extra['label'] ?? $labelKey, $priority, $protect, $data, $threshold),
            'priority'  => $priority,
            'protect'   => $protect,
        ];
    }

    /**
     * Etiketin taban önceliği ile Gemini'nin (yeterince emin olduğu) örnek-bazlı
     * değerlendirmesinden büyük olanı döner — Gemini bir örneği taban değerin
     * ÜSTÜNE çıkarabilir, asla ALTINA düşüremez (ör. bir logo'yu yanlışlıkla
     * "low" işaretleyip korumasız bırakamaz).
     */
    private function maxPriority(string $default, ?string $instance): string
    {
        $order = GarmentLabel::PRIORITY_ORDER;
        $default = strtolower($default);
        $defaultRank = $order[$default] ?? $order[GarmentLabel::PRIORITY_MEDIUM];

        if ($instance === null) {
            return array_key_exists($default, $order) ? $default : GarmentLabel::PRIORITY_MEDIUM;
        }

        $instance = strtolower($instance);
        $instanceRank = $order[$instance] ?? -1;

        return $instanceRank > $defaultRank ? $instance : $default;
    }

    /**
     * @param  array<string,mixed>|null  $data
     */
    private function buildStatement(string $label, string $priority, bool $protect, ?array $data, float $threshold): ?string
    {
        $confidentFields = [];
        if (is_array($data)) {
            foreach (['color', 'pattern', 'texture', 'fabric', 'stitching', 'hardware_type'] as $field) {
                $f = $data[$field] ?? null;
                if (! is_array($f) || (float) ($f['confidence'] ?? 0) < $threshold) {
                    continue;
                }
                $value = ($f['value'] ?? null) === 'OTHER' ? ($f['raw_text'] ?? null) : ($f['value'] ?? null);
                if ($value) {
                    $confidentFields[] = strtolower(str_replace('_', ' ', $field)) . ': ' . strtolower(str_replace('_', ' ', (string) $value));
                }
            }
        }

        // Ne güvenilir bir analiz alanı var ne de kritik/yüksek öncelik — söylenecek
        // ek bir şey yok, describeExtras() eski (G.1-G.3) davranışına düşer.
        if ($confidentFields === [] && ! $protect) {
            return null;
        }

        $prefix = $protect
            ? "For the '{$label}' part (" . strtoupper($priority) . ' — do not redesign, replace, or invent): '
            : "For the '{$label}' part: ";

        $body = $confidentFields !== []
            ? implode(', ', $confidentFields) . '. Preserve exactly as shown in the reference image.'
            : 'preserve exactly as shown in the reference image.';

        return $prefix . $body;
    }
}
