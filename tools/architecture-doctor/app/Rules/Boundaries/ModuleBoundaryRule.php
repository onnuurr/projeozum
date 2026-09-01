<?php

namespace ArchitectureDoctor\Rules\Boundaries;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;
use Symfony\Component\Process\Process;

/**
 * deptrac.php'de tanımlı modül sınırlarını çalıştırıp sonucu Rule Engine'in ortak
 * Finding formatına çevirir — deptrac.baseline.yaml'da dondurulmuş mevcut borç (Product'ın
 * Tenant/Atelier'e olan bağımlılığı) burada Finding üretmez, sadece BUNDAN SONRA eklenecek
 * yeni ihlaller görünür.
 *
 * Maturity::Experimental olarak başlar — birkaç gün false-positive çıkmadığı doğrulanınca
 * Maturity::Stable'a yükseltilip Policy'de fiilen CI'ı bloklamaya başlayacak (bkz. Faz 1 planı).
 */
final class ModuleBoundaryRule implements ArchitectureRule
{
    public function id(): string
    {
        return 'module-boundary.deptrac';
    }

    public function category(): string
    {
        return 'Module Boundaries';
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
        // deptrac, PHPStan'ın reflection motorunu kullanıyor; o da DI container
        // cache'ini sys_get_temp_dir() (varsayılan /tmp) altına yazıyor. Paylaşılan
        // /tmp'taki bir dizin başka bir kullanıcı (ör. root) tarafından oluşturulmuş
        // olabilir ve web sunucusu kullanıcısının (ör. ozumserver) yazma izni
        // olmayabilir — bu yüzden TMPDIR'ı proje içindeki, storage/ ile aynı
        // sahiplikteki bir klasöre sabitliyoruz.
        $tmpDir = storage_path('app/deptrac-tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $process = new Process(
            [base_path('vendor/bin/deptrac'), 'analyse', '--no-progress', '--formatter=json'],
            base_path(),
            ['TMPDIR' => $tmpDir],
            timeout: 120,
        );

        $process->run();

        $decoded = json_decode($process->getOutput(), associative: true);

        if (! is_array($decoded) || ! isset($decoded['files'])) {
            return [new Finding(
                message: 'deptrac çalıştırılamadı veya beklenmeyen çıktı üretti.',
                suggestion: trim($process->getErrorOutput()) ?: 'vendor/bin/deptrac analyse komutunu elle çalıştırıp hatayı inceleyin.',
            )];
        }

        $findings = [];

        foreach ($decoded['files'] as $file => $info) {
            foreach ($info['messages'] ?? [] as $message) {
                $findings[] = new Finding(
                    message: $message['message'],
                    file: $file,
                    line: $message['line'] ?? null,
                    suggestion: "deptrac.php'deki ruleset'i güncelleyin ya da bağımlılığı kaldırın (bkz. module.json architecture.allowed_dependencies).",
                );
            }
        }

        return $findings;
    }
}
