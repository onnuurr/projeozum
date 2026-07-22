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
 *    (yüz yapısı, burun, göz, ten, saç, saç stili, ayırt edici detay). Profil
 *    varsayılan Türk (Anadolu/buğday); bir manken alanı verilirse o parça
 *    kullanıcı değeriyle EZİLİR. Ardından sabit bir "distinctiveness" yönergesi
 *    gelir — metindeki varyant farkının görsele yeterince yansımasını zorlar
 *    (aksi halde aynı yaş/cinsiyetteki mankenler "ikiz" gibi çıkabiliyor).
 *  - [Dinamik] yaş/vücut/ölçü/ek tarif.
 *  - [Gerçekçilik + Işık/Kamera] PromptDirectives ile paylaşılan sabit çapalar.
 *
 * Operatör bir referans fotoğraf yüklerse [Kimlik Duvarı] devre dışı kalır:
 * amaç o kişinin kimliğini kopyalamak değil, image-to-image ile gerçekçilik
 * (ten/ışık/doku) çapası almaktır — bkz. referenceAnchor().
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
        $hasRef  = $request->referencePhotoPath !== null;

        $lines = [
            sprintf(
                'Generate a single, full-body, editorial high-end studio catalog photograph of %s modeling clothing. The subject is unmistakably %s.',
                $subject,
                $subject,
            ),
            $hasRef ? $this->referenceAnchor() : $this->identity($request),
            $this->distinctiveness($hasRef),
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
            // Sıra: yüz yapısı → burun → göz → ten → saç → saç stili → ayırt edici detay.
            $face !== '' ? $face : ($profile['face_structure'] ?? null),
            $profile['nose'] ?? null,
            $profile['eyes'] ?? null,
            $skin !== '' ? sprintf('a %s skin tone', $skin) : ($profile['skin'] ?? null),
            $hair !== '' ? sprintf('%s hair', $hair) : ($profile['hair'] ?? null),
            isset($profile['hairstyle']) ? sprintf('the hair styled %s', $profile['hairstyle']) : null,
            $profile['distinguishing_feature'] ?? null,
        ]);

        if ($parts === []) {
            return null;
        }

        return 'The model has ' . $this->joinList($parts) . '.';
    }

    /**
     * Aynı yaş/cinsiyetten üretilen mankenlerin "kardeş/ikiz" gibi birbirine
     * benzemesini önleyen sabit yönerge. identityProfile() zaten her üretimde
     * farklı varyant seçiyor; bu satır modele o farkı görsel olarak ABARTMASINI
     * söyler — aksi halde metindeki ince farklar görsele yeterince yansımayabilir.
     *
     * Referans fotoğraf verilmişken metin farklı: burada amaç kimlik çeşitliliği
     * değil, referanstaki gerçek kişinin birebir kopyalanmaması (bkz. referenceAnchor).
     */
    private function distinctiveness(bool $hasReferencePhoto = false): string
    {
        if ($hasReferencePhoto) {
            return 'The generated person is a new, original individual — not a copy of the reference photograph\'s '
                . 'specific identity. Do not default to a generic, interchangeable "stock catalog model" appearance; '
                . 'this must read as one specific, unique real person with their own coherent facial identity.';
        }

        return 'This is one specific, unique individual: their facial identity, proportions and overall '
            . 'look must be entirely their own, clearly and visibly distinct from any other generated model. '
            . 'Do not default to a generic, interchangeable "stock catalog model" appearance — this person must '
            . 'not look like a twin, sibling or close relative of another model even if age, gender and ethnic '
            . 'background match.';
    }

    /**
     * Referans fotoğraf verildiğinde kullanılır — identity()'nin yerini alır.
     *
     * Amaç kimlik kopyalama değil GERÇEKÇİLİK: ekli fotoğraf yalnızca ten
     * tonu/ışık/doku/fotografik zemin için görsel bir çapa olarak kullanılır.
     * Referanstaki kişinin yüzü birebir kopyalanmaz (bkz. distinctiveness) —
     * hem consent/KVKK riskini azaltır hem de "twin" sorununu tekrar üretmez.
     */
    private function referenceAnchor(): string
    {
        return 'A reference photograph is attached purely as a visual anchor for photographic realism: use it to '
            . 'guide realistic skin texture and tone, natural lighting behavior and true-to-life photographic '
            . 'grounding. Do NOT reproduce the exact facial identity of the person in the reference photo — '
            . 'generate a different, original individual, but match the reference\'s level of authentic, '
            . 'unretouched photographic realism.';
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
     * Her parça config'te bir varyant LİSTESİdir; burada her parçadan rastgele
     * bir varyant seçilir. Bu sayede aynı profildeki mankenler etnik/ten
     * çapasını korurken birebir aynı yüze ("kardeş" gibi) sahip olmaz.
     *
     * @return array<string,string>
     */
    private function identityProfile(): array
    {
        $key      = (string) config('creative.ai.prompt.default_identity_profile', 'turkish_anatolian');
        $profiles = (array) config('creative.ai.prompt.identity_profiles', []);
        $profile  = (array) ($profiles[$key] ?? []);

        return array_filter(
            array_map(fn ($variants) => $this->pickVariant($variants), $profile),
            fn ($v) => is_string($v) && trim($v) !== '',
        );
    }

    /**
     * Bir kimlik parçası config'te ya tek bir sabit metin ya da varyant
     * listesi olabilir; liste ise rastgele bir varyant seçilir.
     */
    private function pickVariant(mixed $variants): ?string
    {
        if (is_string($variants)) {
            return trim($variants) !== '' ? $variants : null;
        }

        if (! is_array($variants) || $variants === []) {
            return null;
        }

        $list = array_values(array_filter($variants, fn ($v) => is_string($v) && trim($v) !== ''));

        return $list === [] ? null : $list[array_rand($list)];
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
