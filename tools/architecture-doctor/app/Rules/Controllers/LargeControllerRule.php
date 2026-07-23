<?php

namespace ArchitectureDoctor\Rules\Controllers;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;

/**
 * Bir kesinlik değil bir metrik uyarısıdır — satır sayısı yüksek bir controller'ın
 * mutlaka hatalı olduğu anlamına gelmez, ama "büyümüş, bölünmeyi hak ediyor olabilir"
 * sinyali verir. Bu yüzden kalıcı olarak rapor-only tasarlandı: asla Policy'de blocking
 * hale getirilmez, asla Maturity::Stable'a yükseltilmez (bkz. plan Faz 4).
 *
 * Eşik keyfi değil, mevcut dağılıma göre seçildi: Faz 4 planlanırken 250 satırın
 * üzerinde 5 controller vardı (SettingsController, TryonController, ProductController,
 * CreativeStudioController, PatternController) — bunlar "keşfedilmiş borç" olarak ilk
 * günden görünür, baseline'a alınmadı.
 */
final class LargeControllerRule implements ArchitectureRule
{
    private const LINE_THRESHOLD = 250;

    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'controller.large-controller';
    }

    public function category(): string
    {
        return 'Controllers';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle(Maturity::Experimental, '2026-07-23');
    }

    public function check(): array
    {
        $findings = [];

        foreach (ControllerFileScanner::files($this->paths) as $file) {
            $lineCount = substr_count($file->getContents(), "\n");

            if ($lineCount <= self::LINE_THRESHOLD) {
                continue;
            }

            $findings[] = new Finding(
                message: "{$file->getFilenameWithoutExtension()} {$lineCount} satır — ".self::LINE_THRESHOLD.' satır eşiğinin üzerinde, bölünmeyi değerlendirin.',
                file: ControllerFileScanner::relativePath($file->getPathname()),
                suggestion: 'İş kuralı/sorgu barındıran metotları Service sınıflarına taşımayı, ya da controller\'ı ilgili alt-kaynaklara bölmeyi değerlendirin.',
            );
        }

        return $findings;
    }
}
