<?php

namespace Modules\Superadmin\Services\Translations;

use App\Contracts\TranslationSource;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Süper Admin › Dil Dosyaları: container'da App\Contracts\TranslationSource::TAG etiketiyle
 * kayıtlı kaynakları (SaaS, Bagisto) tek yerden sunar. Dosya işi kaynaklardadır.
 */
class TranslationService
{
    /** Tek kayıtta yazılabilecek en fazla değer (dil × anahtar). */
    public const MAX_CHANGES = 5000;

    /** @var array<string, TranslationSource> */
    private array $sources = [];

    /** @param  iterable<TranslationSource>  $sources */
    public function __construct(iterable $sources)
    {
        foreach ($sources as $source) {
            $this->sources[$source->key()] = $source;
        }
        // Kendi projemiz (saas) listede hep önce gelir; kayıt sırası provider yükleme sırasına bağlı.
        uksort($this->sources, fn (string $a, string $b) => ($b === 'saas') <=> ($a === 'saas') ?: strcmp($a, $b));
    }

    /**
     * @return list<array{key: string, label: string, allowsNewFiles: bool}>
     */
    public function sources(): array
    {
        return array_values(array_map(fn (TranslationSource $s) => [
            'key' => $s->key(),
            'label' => $s->label(),
            'allowsNewFiles' => $s->allowsNewFiles(),
        ], $this->sources));
    }

    public function source(string $key): TranslationSource
    {
        return $this->sources[$key] ?? throw new InvalidArgumentException("Dil kaynağı bulunamadı: {$key}");
    }

    /**
     * @param  array<string, array<string, string|null>>  $changes  locale => [dot.key => değer]
     */
    public function save(string $source, string $group, string $file, array $changes, bool $fillMissingLocales, ?int $actorId): int
    {
        $changes = array_filter($changes, fn ($values) => is_array($values) && $values !== []);
        $count = array_sum(array_map('count', $changes));

        if ($count === 0) {
            throw new InvalidArgumentException('Kaydedilecek değişiklik yok.');
        }
        if ($count > self::MAX_CHANGES) {
            throw new InvalidArgumentException('Tek seferde en fazla '.self::MAX_CHANGES.' değer kaydedilebilir.');
        }

        $written = $this->source($source)->write($group, $file, $changes, $fillMissingLocales, $actorId);

        Log::info('Dil dosyası güncellendi', [
            'source' => $source,
            'group' => $group,
            'file' => $file,
            'locales' => array_keys($changes),
            'written' => $written,
            'user_id' => $actorId,
        ]);

        return $written;
    }
}
