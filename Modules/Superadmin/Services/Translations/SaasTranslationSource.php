<?php

namespace Modules\Superadmin\Services\Translations;

use App\Contracts\TranslationSource;
use InvalidArgumentException;

/**
 * Bu projenin (SaaS) dil dosyaları: kök lang/ ve her modülün Modules/<Ad>/lang klasörü.
 * Modül dosyaları AppServiceProvider'da modülün alias'ıyla kaydedilir → __('product::dosya.anahtar')
 * (nwidart ayrıca isim alanısız global de yükler; alias'lı kullanım tercih edilmeli).
 * Klasör/dosya yoksa ilk kayıtta oluşturulur (allowsNewFiles).
 */
class SaasTranslationSource implements TranslationSource
{
    /** Dosyası henüz olmasa da her grupta sunulan diller. */
    private const DEFAULT_LOCALES = ['tr', 'en'];

    public function __construct(private readonly LangFileStore $store) {}

    public function key(): string
    {
        return 'saas';
    }

    public function label(): string
    {
        return 'SaaS';
    }

    public function allowsNewFiles(): bool
    {
        return true;
    }

    public function groups(): array
    {
        $groups = [$this->describe('lang', 'Uygulama (lang/)', "__('dosya.anahtar') · Laravel çekirdek mesajlarını da ezer", base_path('lang'))];

        foreach (glob(base_path('Modules/*/module.json')) ?: [] as $manifest) {
            $module = basename(dirname($manifest));
            $alias = (string) (json_decode((string) file_get_contents($manifest), true)['alias'] ?? strtolower($module));

            $groups[] = $this->describe('Modules/'.$module, $module, "__('{$alias}::dosya.anahtar')", base_path('Modules/'.$module.'/lang'));
        }

        return $groups;
    }

    public function read(string $group, string $file, array $locales): array
    {
        $dir = $this->resolve($group, $file, $locales);

        return $this->store->read($dir, $file, $locales);
    }

    public function write(string $group, string $file, array $changes, bool $fillMissingLocales, ?int $actorId): int
    {
        $dir = $this->resolve($group, $file, array_keys($changes));
        $fill = $fillMissingLocales ? $this->meta($group)['locales'] : [];

        return $this->store->write($dir, $file, $changes, $fill);
    }

    /**
     * @param  list<string>  $locales
     */
    private function resolve(string $group, string $file, array $locales): string
    {
        $meta = $this->meta($group);

        if (! preg_match(LangFileStore::FILE_NAME, $file)) {
            throw new InvalidArgumentException('Dosya adı yalnız harf, rakam, "-" ve "_" içerebilir.');
        }

        foreach ($locales as $locale) {
            if (! in_array($locale, $meta['locales'], true)) {
                throw new InvalidArgumentException("Geçersiz dil: {$locale}");
            }
        }

        return $group === 'lang' ? base_path('lang') : base_path($group.'/lang');
    }

    /**
     * @return array{key: string, label: string, hint: string|null, locales: list<string>, files: list<string>}
     */
    private function meta(string $group): array
    {
        foreach ($this->groups() as $meta) {
            if ($meta['key'] === $group) {
                return $meta;
            }
        }

        throw new InvalidArgumentException("Dil grubu bulunamadı: {$group}");
    }

    private function describe(string $key, string $label, string $hint, string $dir): array
    {
        $found = $this->store->describe($dir);
        $locales = array_values(array_unique([...self::DEFAULT_LOCALES, ...$found['locales']]));

        return ['key' => $key, 'label' => $label, 'hint' => $hint, 'locales' => $locales, 'files' => $found['files']];
    }
}
