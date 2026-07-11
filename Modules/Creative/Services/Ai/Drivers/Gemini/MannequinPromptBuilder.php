<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\MannequinRequest;

/**
 * MannequinRequest'ten Gemini kimlik prompt'u üretir.
 *
 * Çıktı, sonraki try-on aşaması için uygun olacak şekilde NÖTR planlanır:
 * tam boy, cepheden, sade arka plan, üzerinde giysi değişimine uygun basit
 * taban kıyafet. Bu görsel tüm pozlara kimlik referansı olur.
 *
 * Prompt SABİT/DİNAMİK olarak kurgulanır (bkz. creative.ai.prompt):
 *  - [Kimlik Duvarı] cinsiyet/yaş özne isminde + yapılandırılabilir kimlik profili
 *    (yüz yapısı, ten, göz, saç). Profil varsayılan Türk (Anadolu/buğday); bir
 *    manken alanı verilirse o parça kullanıcı değeriyle EZİLİR.
 *  - [Dinamik] yaş/vücut/ölçü/ek tarif.
 *  - [Gerçekçilik + Işık/Kamera] PromptDirectives ile paylaşılan sabit çapalar.
 *
 * Cinsiyet ve yaş, virgüllü sıfat listesine gömülmek yerine ÖZNE ismine yazılır
 * (ör. "a young girl", "an adult man") — görsel modelleri özne ismine çok daha
 * güçlü ağırlık verdiği için kız/erkek ve çocuk/yetişkin ayrımı böyle güvenilir olur.
 */
class MannequinPromptBuilder
{
    public function build(MannequinRequest $request): string
    {
        if ($request->promptOverride) {
            return $request->promptOverride;
        }

        $subject = $this->subjectNoun($request);
        $isChild = $this->isChild($request->ageRange);

        $lines = [
            sprintf(
                'Generate a single, full-body, editorial high-end studio catalog photograph of %s modeling clothing. The subject is unmistakably %s.',
                $subject,
                $subject,
            ),
            $this->identity($request),
            $this->expression($request),
            $this->descriptors($request),
            $this->measurements($request, $isChild),
            'Standing upright and facing the camera in a neutral, relaxed pose, arms slightly away from the body, the entire body visible from head to feet.',
            'Keep the background clean, seamless, minimal and free of props.',
            'The subject wears simple, plain, neutral-tone fitted base clothing suitable for virtual garment try-on (a clean minimal baseline outfit).',
            PromptDirectives::realism(),
            PromptDirectives::camera(),
            'One real human being only, centered in frame, no text, no watermark, no logo.',
        ];

        return implode(' ', array_filter($lines));
    }

    /**
     * [Kimlik Duvarı] — yapılandırılabilir kimlik profili (yüz/göz/ten/saç).
     * Manken alanı verilmişse ilgili parçayı kullanıcı değeriyle EZER; böylece
     * profil "varsayılan ama override edilebilir" olur ve çift-talimat oluşmaz.
     */
    private function identity(MannequinRequest $request): ?string
    {
        $profile = $this->identityProfile();

        $face = trim((string) $request->face);
        $skin = trim((string) $request->skinTone);
        $hair = trim((string) $request->hair);

        $parts = array_filter([
            // Sıra: yüz yapısı → göz → ten → saç.
            $face !== '' ? $face : ($profile['face_structure'] ?? null),
            $profile['eyes'] ?? null,
            $skin !== '' ? sprintf('a %s skin tone', $skin) : ($profile['skin'] ?? null),
            $hair !== '' ? sprintf('%s hair', $hair) : ($profile['hair'] ?? null),
        ]);

        if ($parts === []) {
            return null;
        }

        return 'The model has ' . $this->joinList($parts) . '.';
    }

    /**
     * İfade: çocuk/genç için sıcak, doğal gülümseme varsayılanı; yetişkinde
     * zorlanmaz (nötr katalog). Kullanıcı `face` yazmışsa ifadeyi orada
     * belirlemiş olabileceğinden bu varsayılan eklenmez.
     */
    private function expression(MannequinRequest $request): ?string
    {
        if (trim((string) $request->face) !== '') {
            return null;
        }

        return in_array($this->ageBand($request->ageRange), ['child', 'teen'], true)
            ? 'The expression is a warm, genuine, natural smile that softly crinkles the skin around the eyes.'
            : null;
    }

    /**
     * Aktif kimlik profilinin yapısal parçaları (config). Bilinmeyen profil → boş.
     *
     * @return array<string,string>
     */
    private function identityProfile(): array
    {
        $key      = (string) config('creative.ai.prompt.default_identity_profile', 'turkish_anatolian');
        $profiles = (array) config('creative.ai.prompt.identity_profiles', []);

        return array_filter((array) ($profiles[$key] ?? []), fn ($v) => is_string($v) && trim($v) !== '');
    }

    /**
     * Cinsiyet + yaş'tan güçlü bir özne ismi kurar (kız/erkek ve çocuk/genç/
     * yetişkin ayrımının taşıyıcısı). Bu, prompt'un en belirleyici parçasıdır.
     */
    private function subjectNoun(MannequinRequest $request): string
    {
        $band  = $this->ageBand($request->ageRange);     // child | teen | adult
        $g     = $this->normalizeGender($request->gender); // female | male | neutral | <ham>

        // Bilinen cinsiyetler için yaşa göre net isim.
        $map = [
            'child' => ['female' => 'a young girl', 'male' => 'a young boy',  'neutral' => 'a young child'],
            'teen'  => ['female' => 'a teenage girl', 'male' => 'a teenage boy', 'neutral' => 'a teenager'],
            'adult' => ['female' => 'an adult woman', 'male' => 'an adult man', 'neutral' => 'an adult person'],
        ];

        if (isset($map[$band][$g])) {
            return $map[$band][$g];
        }

        // Serbest-metin cinsiyet (ör. doğrudan yazılmış): ham değeri özneye koy.
        $prefix = $band === 'child' ? 'a young ' : ($band === 'teen' ? 'a teenage ' : 'an adult ');

        return $prefix . $g . ' person';
    }

    /**
     * Cinsiyet/ten/saç HARİÇ ek görünüm sıfatları (yaş detayı, vücut). Ten ve saç
     * kimlik duvarında verildiği için burada tekrarlanmaz. Boşlar atlanır.
     */
    private function descriptors(MannequinRequest $request): ?string
    {
        $traits = array_filter([
            $request->ageRange ? sprintf('around %s years old', $request->ageRange) : null,
            $request->bodyType ? sprintf('%s build', $request->bodyType) : null,
        ]);

        $base = $traits === []
            ? null
            : 'Appearance details: ' . implode(', ', $traits) . '.';

        if ($extras = trim((string) $request->extras)) {
            $base = $base === null ? $extras : $base . ' ' . $extras;
        }

        return $base;
    }

    /**
     * Vücut ölçüleri — verilenleri doğal dile çevirir. Çocukta göğüs/bel/kalça
     * ölçüleri ANLAMSIZ ve yetişkine kayma riski taşıdığı için atlanır; yalnız
     * boy korunur.
     */
    private function measurements(MannequinRequest $request, bool $isChild): ?string
    {
        $parts = array_filter([
            $request->heightCm ? sprintf('height about %d cm', $request->heightCm) : null,
            $isChild ? null : ($request->bustCm ? sprintf('bust about %d cm', $request->bustCm) : null),
            $isChild ? null : ($request->waistCm ? sprintf('waist about %d cm', $request->waistCm) : null),
            $isChild ? null : ($request->hipsCm ? sprintf('hips about %d cm', $request->hipsCm) : null),
        ]);

        if ($parts === []) {
            return null;
        }

        return 'Body measurements to reflect in proportions: ' . implode(', ', $parts) . '.';
    }

    /**
     * Parçaları "a, b, c and d" biçiminde birleştirir.
     *
     * @param  array<int,string>  $parts
     */
    private function joinList(array $parts): string
    {
        $parts = array_values($parts);
        $count = count($parts);

        if ($count === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts) . ' and ' . $last;
    }

    /**
     * Cinsiyet ipucunu normalize eder: female | male | neutral | <ham>.
     * "unisex" anlamsız bir kişi-sıfatı olduğundan nötr sayılır (özneye girmez).
     */
    private function normalizeGender(?string $gender): string
    {
        $g = strtolower(trim((string) $gender));

        return match (true) {
            $g === '' || $g === 'unisex' || $g === 'androgynous' => 'neutral',
            in_array($g, ['female', 'woman', 'kadın', 'kız', 'f'], true) => 'female',
            in_array($g, ['male', 'man', 'erkek', 'm'], true)           => 'male',
            default => $g,
        };
    }

    /**
     * Yaş aralığının baş rakamından yaş bandı: child (<13) | teen (<18) | adult.
     * Boş/çözülemezse 'adult' kabul edilir.
     */
    private function ageBand(?string $ageRange): string
    {
        $age = $this->parseAge($ageRange);

        return match (true) {
            $age === null => 'adult',
            $age < 13     => 'child',
            $age < 18     => 'teen',
            default       => 'adult',
        };
    }

    private function isChild(?string $ageRange): bool
    {
        return $this->ageBand($ageRange) === 'child';
    }

    /**
     * "6-9", "18-25", "45+" gibi değerlerden baştaki tam sayıyı çıkarır.
     */
    private function parseAge(?string $ageRange): ?int
    {
        if (! preg_match('/\d+/', (string) $ageRange, $m)) {
            return null;
        }

        return (int) $m[0];
    }
}
