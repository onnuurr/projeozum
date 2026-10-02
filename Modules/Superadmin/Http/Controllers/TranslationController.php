<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Superadmin\Http\Requests\UpdateTranslationsRequest;
use Modules\Superadmin\Services\Translations\TranslationService;
use RuntimeException;

/**
 * Süper Admin › Dil Dosyaları: SaaS (lang/ + Modules/*\/lang) ve Bagisto (paket
 * Resources/lang + lang/vendor override'ları) PHP dil dosyalarını görüntüleme, düzenleme
 * ve anahtar ekleme. Seçim (kaynak/grup/dosya/diller) URL sorgusunda tutulur.
 */
class TranslationController extends Controller
{
    /** Sorguda dil verilmezse gösterilen diller (grupta varsa). */
    private const DEFAULT_LOCALES = ['tr', 'en'];

    /** Aynı anda en fazla kaç dil sütunu. */
    private const MAX_LOCALES = 4;

    public function __construct(private readonly TranslationService $translations) {}

    public function index(Request $request): Response
    {
        $sources = $this->translations->sources();
        $sourceKey = collect($sources)->firstWhere('key', $request->query('source'))['key'] ?? $sources[0]['key'];

        $groups = [];
        $group = null;
        $file = null;
        $locales = [];
        $rows = null;
        $error = null;

        try {
            $source = $this->translations->source($sourceKey);
            $groups = $source->groups();
            $group = collect($groups)->firstWhere('key', $request->query('group')) ?? ($groups[0] ?? null);

            if ($group) {
                $file = $this->pickFile($request->query('file'), $group, $source->allowsNewFiles());
                $locales = $this->pickLocales((string) $request->query('locales', ''), $group['locales']);

                if ($file !== null) {
                    $rows = $source->read($group['key'], $file, $locales);
                }
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Superadmin::Translations', [
            'sources' => $sources,
            'groups' => $groups,
            'selection' => [
                'source' => $sourceKey,
                'group' => $group['key'] ?? null,
                'file' => $file,
                'locales' => $locales,
            ],
            'rows' => $rows,
            'isNewFile' => $file !== null && $group !== null && ! in_array($file, $group['files'], true),
            'maxLocales' => self::MAX_LOCALES,
            'error' => $error,
        ]);
    }

    public function update(UpdateTranslationsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $written = $this->translations->save(
                $data['source'],
                $data['group'],
                $data['file'],
                $request->changes(),
                (bool) ($data['fill_missing_locales'] ?? false),
                $request->user()?->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$written} çeviri kaydedildi.");
    }

    /**
     * Sorgudaki dosya grupta varsa o; yoksa kaynak yeni dosyaya izin veriyorsa ve ad geçerliyse
     * (yeni dosya, ilk kayıtta oluşur) o; aksi halde grubun ilk dosyası.
     *
     * @param  array{files: list<string>}  $group
     */
    private function pickFile(mixed $requested, array $group, bool $allowsNewFiles): ?string
    {
        if (is_string($requested) && in_array($requested, $group['files'], true)) {
            return $requested;
        }

        if ($allowsNewFiles && is_string($requested) && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $requested)) {
            return $requested;
        }

        return $group['files'][0] ?? null;
    }

    /**
     * @param  list<string>  $available
     * @return list<string>
     */
    private function pickLocales(string $requested, array $available): array
    {
        $wanted = array_values(array_intersect(array_filter(explode(',', $requested)), $available));

        if ($wanted === []) {
            $wanted = array_values(array_intersect(self::DEFAULT_LOCALES, $available)) ?: array_slice($available, 0, 2);
        }

        return array_slice(array_values(array_unique($wanted)), 0, self::MAX_LOCALES);
    }
}
