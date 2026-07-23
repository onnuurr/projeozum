<?php

namespace ArchitectureDoctor\Rules\Migrations;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;

/**
 * CLAUDE.md "down() gerçek ters işlemi yapsın" maddesini otomatik denetler. introducedIn
 * tarihi filtresi kasıtlı olarak YOK: boş down() geçmişte de yanlıştı, bugün de yanlış —
 * ama mevcut migration'ların hiçbiri ihlal etmediği için bu retroaktif katılık gönderildiği
 * gün sorun çıkarmaz (bkz. plan Faz 2 bulguları).
 *
 * EXCLUDED_MIGRATIONS: paylaşılan framework altyapısı için bilinçli no-op down() istisnası
 * (SoftDeleteShouldBePrunableRule::EXCLUDED_MODELS ile aynı desen). Örnek:
 * create_notifications_table — tablo prod'da bu migration'dan önce de vardı, geri alımda
 * DROP edilmiyor.
 */
final class MigrationDownIsRealRule implements ArchitectureRule
{
    private const EXCLUDED_MIGRATIONS = [
        '2026_06_24_110000_create_notifications_table',
    ];

    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'migration.down-is-real';
    }

    public function category(): string
    {
        return 'Migrations';
    }

    public function severity(): Severity
    {
        return Severity::Critical;
    }

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle(Maturity::Experimental, '2026-07-23');
    }

    public function check(): array
    {
        $findings = [];

        foreach (MigrationFileScanner::files($this->paths) as $file) {
            if (in_array($file->getFilenameWithoutExtension(), self::EXCLUDED_MIGRATIONS, true)) {
                continue;
            }

            $body = MigrationFileScanner::methodBody($file->getContents(), 'down');

            if ($body === null) {
                $findings[] = new Finding(
                    message: 'Migration bir down() metodu tanımlamıyor.',
                    file: MigrationFileScanner::relativePath($file->getPathname()),
                    suggestion: "up()'ı tersine çeviren gerçek bir down() ekleyin (bkz. CLAUDE.md 'Veritabanı değişiklik disiplini').",
                );

                continue;
            }

            if ($this->isNoOp($body)) {
                $findings[] = new Finding(
                    message: 'down() metodu boş/no-op — up() tersine çevrilmiyor.',
                    file: MigrationFileScanner::relativePath($file->getPathname()),
                    suggestion: "up()'ı tersine çeviren gerçek bir down() yazın (bkz. CLAUDE.md 'Veritabanı değişiklik disiplini').",
                );
            }
        }

        return $findings;
    }

    private function isNoOp(string $body): bool
    {
        $stripped = preg_replace('#/\*.*?\*/#s', '', $body);
        $stripped = preg_replace('#//[^\n]*#', '', (string) $stripped);

        return trim((string) $stripped) === '';
    }
}
