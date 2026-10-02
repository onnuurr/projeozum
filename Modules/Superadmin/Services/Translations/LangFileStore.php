<?php

namespace Modules\Superadmin\Services\Translations;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Laravel PHP dil dosyalarını (<dir>/<locale>/<file>.php → return [...]) okur ve yazar.
 * Dil klasörünü çağıran belirler ve doğrular (SaasTranslationSource); bu sınıf yalnız dosya işini yapar.
 *
 * Yazarken dosya baştan üretilir (yorum/hizalama korunmaz, veri birebir korunur), önce
 * storage/app/translation-backups/<zaman>/ altına yedeklenir, geçici dosya + rename ile
 * atomik yazılır ve opcache geçersiz kılınır.
 *
 * Bagisto ucundaki eşi: packages/Ozum/SaasSync/src/Services/TranslationFiles.php — biri
 * değişirse diğeri de güncellenmeli.
 */
class LangFileStore
{
    public const KEY_SEGMENT = '/^[A-Za-z0-9_\-]+$/';

    public const FILE_NAME = '/^[A-Za-z0-9_\-]+$/';

    public const LOCALE = '/^[A-Za-z]{2,3}(_[A-Za-z]{2,4})?$/';

    /** @param  string|null  $backupRoot  yedeklerin kökü (varsayılan storage/app/translation-backups) */
    public function __construct(private readonly ?string $backupRoot = null) {}

    /**
     * @return array{locales: list<string>, files: list<string>}
     */
    public function describe(string $dir): array
    {
        $locales = [];
        $files = [];

        foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $localeDir) {
            $locale = basename($localeDir);
            if (! preg_match(self::LOCALE, $locale)) {
                continue; // lang/vendor gibi alt klasörler dil değildir
            }
            $locales[] = $locale;
            foreach (glob($localeDir.'/*.php') ?: [] as $php) {
                $files[basename($php, '.php')] = true;
            }
        }

        sort($locales);
        $files = array_keys($files);
        sort($files);

        return ['locales' => $locales, 'files' => $files];
    }

    /**
     * @param  list<string>  $locales
     * @return list<array{key: string, values: array<string, string|null>, editable: bool}>
     */
    public function read(string $dir, string $file, array $locales): array
    {
        $perLocale = [];
        foreach ($locales as $locale) {
            $perLocale[$locale] = $this->flatten($this->load($this->path($dir, $locale, $file)));
        }

        $keys = [];
        foreach ($perLocale as $flat) {
            $keys += $flat;
        }
        ksort($keys, SORT_NATURAL);

        $rows = [];
        foreach ($keys as $key => $_) {
            $values = [];
            $editable = true;
            foreach ($perLocale as $locale => $flat) {
                $entry = $flat[$key] ?? null;
                $values[$locale] = $entry === null ? null : $entry['value'];
                $editable = $editable && ($entry === null || $entry['editable']);
            }
            $rows[] = ['key' => (string) $key, 'values' => $values, 'editable' => $editable];
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, string|null>>  $changes  locale => [dot.key => değer]
     * @param  list<string>  $fillLocales  boş değilse: bu dillerin mevcut dosyalarına eksik anahtarlar aynı değerle eklenir
     * @return int yazılan değer sayısı
     */
    public function write(string $dir, string $file, array $changes, array $fillLocales = []): int
    {
        if ($fillLocales !== []) {
            $changes = $this->withMissingLocales($dir, $file, $changes, $fillLocales);
        }

        $backupDir = ($this->backupRoot ?? storage_path('app/translation-backups')).'/'.now()->format('Y-m-d_His');
        $written = 0;

        foreach ($changes as $locale => $values) {
            $path = $this->path($dir, $locale, $file);
            $data = $this->load($path);

            foreach ($values as $key => $value) {
                $this->set($data, (string) $key, (string) ($value ?? ''));
                $written++;
            }

            $this->save($path, $data, $backupDir);
        }

        return $written;
    }

    private function path(string $dir, string $locale, string $file): string
    {
        return $dir.'/'.$locale.'/'.$file.'.php';
    }

    private function load(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = (static fn (string $p) => include $p)($path);

        return is_array($data) ? $data : [];
    }

    /**
     * Anahtar parçasında nokta/boşluk vb. olan ya da dizi/metin dışı değerler salt-okunur işaretlenir:
     * nokta yolu ile güvenle geri yazılamazlar.
     *
     * @return array<string, array{value: string|null, editable: bool}>
     */
    private function flatten(array $data, string $prefix = '', bool $editable = true): array
    {
        $out = [];

        foreach ($data as $k => $v) {
            $segmentOk = $editable && preg_match(self::KEY_SEGMENT, (string) $k);
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;

            if (is_array($v) && $v !== []) {
                $out += $this->flatten($v, $key, (bool) $segmentOk);
            } else {
                $out[$key] = [
                    'value' => is_scalar($v) ? (string) $v : null,
                    'editable' => $segmentOk && (is_string($v) || $v === null),
                ];
            }
        }

        return $out;
    }

    private function set(array &$data, string $key, string $value): void
    {
        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (! preg_match(self::KEY_SEGMENT, $segment)) {
                throw new InvalidArgumentException("Geçersiz anahtar: {$key}");
            }
        }

        $ref = &$data;
        $last = array_pop($segments);
        foreach ($segments as $segment) {
            if (! isset($ref[$segment])) {
                $ref[$segment] = [];
            } elseif (! is_array($ref[$segment])) {
                throw new InvalidArgumentException("\"{$key}\" eklenemiyor: \"{$segment}\" zaten bir metin.");
            }
            $ref = &$ref[$segment];
        }

        if (isset($ref[$last]) && is_array($ref[$last])) {
            throw new InvalidArgumentException("\"{$key}\" bir grup; metin olarak yazılamaz.");
        }

        $ref[$last] = $value;
    }

    /**
     * @param  array<string, array<string, string|null>>  $changes
     * @param  list<string>  $locales
     * @return array<string, array<string, string|null>>
     */
    private function withMissingLocales(string $dir, string $file, array $changes, array $locales): array
    {
        $seed = [];
        foreach ($changes as $values) {
            $seed += $values; // ilk verilen dilin değeri esas alınır
        }

        foreach ($locales as $locale) {
            $path = $this->path($dir, $locale, $file);
            if (isset($changes[$locale]) || ! is_file($path)) {
                continue;
            }
            $missing = array_diff_key($seed, $this->flatten($this->load($path)));
            if ($missing !== []) {
                $changes[$locale] = $missing;
            }
        }

        return $changes;
    }

    private function save(string $path, array $data, string $backupDir): void
    {
        if (is_file($path)) {
            $backup = $backupDir.'/'.ltrim(str_replace(base_path(), '', $path), '/');
            File::ensureDirectoryExists(dirname($backup));
            File::copy($path, $backup);
        } else {
            File::ensureDirectoryExists(dirname($path));
        }

        $tmp = $path.'.tmp-'.bin2hex(random_bytes(4));
        File::put($tmp, "<?php\n\nreturn [\n".$this->export($data, 1)."];\n");
        if (is_file($path)) {
            @chmod($tmp, fileperms($path) & 0777);
        }
        rename($tmp, $path);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }

    private function export(array $data, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $out = '';

        foreach ($data as $k => $v) {
            $key = is_int($k) ? $k : var_export((string) $k, true);

            if (is_array($v)) {
                $value = $v === [] ? '[]' : "[\n".$this->export($v, $depth + 1).$pad.']';
            } elseif ($v === null) {
                $value = 'null';
            } elseif (is_bool($v)) {
                $value = $v ? 'true' : 'false';
            } else {
                $value = var_export($v, true);
            }

            $out .= $pad.$key.' => '.$value.",\n";
        }

        return $out;
    }
}
