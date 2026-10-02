<?php

namespace App\Contracts;

/**
 * Süper Admin › Dil Dosyaları editörünün düzenleyebildiği bir dil dosyası kaynağı.
 * Superadmin modülü Bagisto modülüne bağımlı olamadığı için (deptrac: Superadmin →
 * Tenant, App) kaynaklar bu kernel sözleşmesi üzerinden, container'da
 * `self::TAG` etiketiyle toplanır:
 *   - saas    → Modules\Superadmin\Services\Translations\SaasTranslationSource (lang/ + Modules/*\/lang)
 *   - bagisto → Modules\Bagisto\Services\BagistoTranslationSource (imzalı api/saas-sync/translations/*)
 *
 * Grup = bir dil klasörü (ör. "Modules/Product", "packages/Webkul/Admin"); dosya = o klasördeki
 * <locale>/<file>.php. Anahtarlar nokta yoludur ("a.b.c"). Geçersiz grup/dosya/dil/anahtar
 * \InvalidArgumentException; kaynağa ulaşılamazsa \RuntimeException fırlatılır.
 */
interface TranslationSource
{
    public const TAG = 'translation.sources';

    /** Kısa, URL'de kullanılan anahtar: "saas" | "bagisto". */
    public function key(): string;

    public function label(): string;

    /** Olmayan bir dosya adıyla kaydetmek o dosyayı oluşturur mu? */
    public function allowsNewFiles(): bool;

    /**
     * @return list<array{key: string, label: string, hint: string|null, locales: list<string>, files: list<string>}>
     */
    public function groups(): array;

    /**
     * @param  list<string>  $locales
     * @return list<array{key: string, values: array<string, string|null>, editable: bool}>
     */
    public function read(string $group, string $file, array $locales): array;

    /**
     * @param  array<string, array<string, string|null>>  $changes  locale => [dot.key => değer]
     * @param  bool  $fillMissingLocales  anahtarı grubun diğer dillerine de (yoksa) aynı değerle ekle
     * @return int yazılan değer sayısı
     */
    public function write(string $group, string $file, array $changes, bool $fillMissingLocales, ?int $actorId): int;
}
