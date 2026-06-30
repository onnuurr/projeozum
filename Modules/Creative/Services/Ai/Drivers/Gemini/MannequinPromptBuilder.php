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
 * Cinsiyet ve yaş, virgüllü bir sıfat listesine gömülmek yerine ÖZNE ismine
 * yazılır (ör. "a young girl", "an adult man") — görsel modelleri özne ismine
 * çok daha güçlü ağırlık verdiği için kız/erkek ve çocuk/yetişkin ayrımı bu
 * sayede güvenilir olur. Ayrıca prompt, fotogerçekçilik çapaları taşır.
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
                'Generate a single ultra-realistic, full-body studio photograph of %s modeling clothing. The subject is unmistakably %s.',
                $subject,
                $subject,
            ),
            $this->realism(),
            $this->descriptors($request),
            $this->face($request),
            $this->measurements($request, $isChild),
            'Standing upright and facing the camera in a neutral relaxed pose, arms slightly away from the body, the entire body visible from head to feet.',
            'Plain seamless light-gray studio background with soft, even, diffused studio lighting and no props.',
            'The subject wears simple plain neutral-tone fitted base clothing suitable for virtual garment try-on (a clean minimal baseline outfit).',
            'One real human being only, centered in frame, no text, no watermark, no logo.',
        ];

        return implode(' ', array_filter($lines));
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
     * Fotogerçekçilik çapaları + CGI/manken/illüstrasyon negatif çapaları.
     */
    private function realism(): string
    {
        return 'Shot on a full-frame DSLR camera with an 85mm portrait lens at f/2.8, '
            . 'soft diffused softbox studio lighting and natural color. '
            . 'Photorealistic skin showing real pores and fine texture, individual hair strands, '
            . 'lifelike eyes with natural catchlights, anatomically correct body and realistic hands with five fingers each, '
            . 'true-to-life human proportions. '
            . 'It must look like a genuine photograph of a real living person — not a 3D render, not CGI, '
            . 'not an illustration, not a painting, not a doll and not a store mannequin.';
    }

    /**
     * Cinsiyet HARİÇ ek görünüm sıfatları (yaş detayı, vücut, ten, saç).
     * Cinsiyet özne isminde verildiği için burada tekrarlanmaz. Boşlar atlanır.
     */
    private function descriptors(MannequinRequest $request): ?string
    {
        $traits = array_filter([
            $request->ageRange ? sprintf('around %s years old', $request->ageRange) : null,
            $request->bodyType ? sprintf('%s build', $request->bodyType) : null,
            $request->skinTone ? sprintf('%s skin tone', $request->skinTone) : null,
            $request->hair ? sprintf('%s hair', $request->hair) : null,
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
     * Yüz tarifi (kimliğin en belirleyici parçası) — verilmişse vurgular.
     */
    private function face(MannequinRequest $request): ?string
    {
        $face = trim((string) $request->face);
        if ($face === '') {
            return null;
        }

        return sprintf('Facial features (keep these consistent and distinctive): %s.', $face);
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
