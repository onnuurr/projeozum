<?php

namespace ArchitectureDoctor\Rules\AiLayer;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;
use Symfony\Component\Finder\Finder;

/**
 * Contract yerine somut AI sürücüsünün (Gemini*, Fal*, Mock*) doğrudan `new` edilmesini ya
 * da constructor'da tip-ipucu olarak inject edilmesini tespit eder — bu, Mock'a
 * geçilememesinin (dolayısıyla testability/DI sınırının) asıl ihlalidir. "Sürücü" seti
 * ServiceProvider'ların FİİLEN bir Contract'a bind ettiği sınıflardan çıkarılır (bkz.
 * AiServiceProviderScanner::driverClassesIn) — bu yüzden GeminiClient/FalClient/
 * GeminiPromptBuilder gibi paylaşılan alt-katman infra sınıfları (Product/Creative AI
 * layer boundary anlaşması) hiçbir Contract'a bind edilmediği için kapsam dışı kalır ve
 * false-positive üretmez.
 *
 * Bir sürücünün KENDİ ServiceProvider'ı ve KENDİ tanım dosyası (PSR-4: dosya adı == sınıf
 * adı) hariç tutulur — aksi halde her sürücü kendi binding'inde/dosyasında kaçınılmaz
 * olarak kendi adını geçirdiği için sürekli FAILED üretirdi.
 */
final class AiConcreteDriverBypassRule implements ArchitectureRule
{
    /**
     * @param  string[]|null  $providerPaths
     * @param  string[]|null  $scanPaths
     */
    public function __construct(
        private readonly ?array $providerPaths = null,
        private readonly ?array $scanPaths = null,
    ) {}

    public function id(): string
    {
        return 'ai-driver.bypass';
    }

    public function category(): string
    {
        return 'AI Layer';
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
        $drivers = $this->collectBoundDrivers();

        if ($drivers === []) {
            return [];
        }

        $findings = [];

        foreach ($this->scanFiles() as $file) {
            $path = $file->getPathname();
            $basename = $file->getFilenameWithoutExtension();
            $stripped = $this->stripUseImports($file->getContents());

            foreach ($drivers as $driver => $providerPath) {
                if ($path === $providerPath || $basename === $driver) {
                    continue;
                }

                if ($this->bypasses($stripped, $driver)) {
                    $findings[] = new Finding(
                        message: "Somut AI sürücüsü {$driver}, Contract'ı bypass ederek doğrudan kullanılıyor.",
                        file: AiServiceProviderScanner::relativePath($path),
                        suggestion: "{$driver}'a doğrudan bağımlı olmak yerine ilgili Contract'ı inject edin (bkz. CreativeServiceProvider'daki config-switch deseni).",
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<string, string> sürücü kısa adı => bind edildiği ServiceProvider'ın tam yolu
     */
    private function collectBoundDrivers(): array
    {
        $drivers = [];

        foreach (AiServiceProviderScanner::providerFiles($this->providerPaths) as $providerFile) {
            foreach (AiServiceProviderScanner::bindCalls($providerFile) as $call) {
                foreach (AiServiceProviderScanner::driverClassesIn($call['body']) as $driver) {
                    $drivers[$driver] ??= $providerFile->getPathname();
                }
            }
        }

        return $drivers;
    }

    /**
     * @return iterable<int, \Symfony\Component\Finder\SplFileInfo>
     */
    private function scanFiles(): iterable
    {
        $paths = array_values(array_filter($this->scanPaths ?? $this->defaultScanPaths(), 'is_dir'));

        if ($paths === []) {
            return [];
        }

        return Finder::create()->files()->name('*.php')->in($paths);
    }

    /**
     * @return string[]
     */
    private function defaultScanPaths(): array
    {
        return array_values(array_filter(
            array_merge([app_path()], glob(base_path('Modules/*'), GLOB_ONLYDIR) ?: []),
            'is_dir',
        ));
    }

    private function bypasses(string $content, string $driver): bool
    {
        $quoted = preg_quote($driver, '/');

        if (preg_match('/\bnew\s+(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*'.$quoted.'\s*\(/', $content)) {
            return true;
        }

        return (bool) preg_match('/\b'.$quoted.'\s+\$[A-Za-z_]/', $content);
    }

    private function stripUseImports(string $content): string
    {
        return preg_replace('/^use\s+[^;]+;\s*$/m', '', $content) ?? $content;
    }
}
