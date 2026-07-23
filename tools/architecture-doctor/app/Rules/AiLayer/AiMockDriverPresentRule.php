<?php

namespace ArchitectureDoctor\Rules\AiLayer;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;

/**
 * Creative/AtelierServiceProvider'ın her ikisinin de sahip olduğu config-tabanlı
 * Gemini/Mock seçim desenini denetler: bir Contract bind()'i en az bir Gemini* veya Fal*
 * sürücüye sahipse (yani gerçek bir AI çağrısı yapılıyorsa), aynı bind() içinde bir
 * Mock* alternatifi de bulunmalı. Bind gövdesinde hiç Gemini/Fal/Mock referansı yoksa
 * (ör. RendererContract → sadece PythonRenderer, ya da Finance'ın
 * EInvoiceProviderInterface'i → Trendyol/Null) bu kuralın kapsamı DIŞINDA sayılır —
 * AI sürücü deseni orada hiç kullanılmıyor demektir.
 *
 * Bugün Product'ın ProductDescriptionGenerator bind()'i (ProductServiceProvider.php)
 * bunu ihlal ediyor: doğrudan GeminiProductDescriptionGenerator'a bağlı, Mock yok,
 * config kontrolü yok (bkz. plan Faz 3 bulguları — bilinçli, ilk günden görünür bulgu,
 * baseline'a alınmıyor).
 */
final class AiMockDriverPresentRule implements ArchitectureRule
{
    /**
     * @param  string[]|null  $providerPaths
     */
    public function __construct(private readonly ?array $providerPaths = null) {}

    public function id(): string
    {
        return 'ai-mock-driver.present';
    }

    public function category(): string
    {
        return 'AI Layer';
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

        foreach (AiServiceProviderScanner::providerFiles($this->providerPaths) as $providerFile) {
            foreach (AiServiceProviderScanner::bindCalls($providerFile) as $call) {
                $drivers = AiServiceProviderScanner::driverClassesIn($call['body']);

                if ($drivers === [] || $this->hasMockDriver($drivers)) {
                    continue;
                }

                $findings[] = new Finding(
                    message: "{$call['contract']} bind()'i hiçbir Mock* sürücüsüne sahip değil — config-switch/test-double yok.",
                    file: AiServiceProviderScanner::relativePath($providerFile->getPathname()),
                    line: $call['line'],
                    suggestion: "Creative/AtelierServiceProvider'daki config-tabanlı Gemini/Mock seçim desenini uygulayın: bind() closure'ı config'e göre bir Mock* sürücüye de düşebilmeli.",
                );
            }
        }

        return $findings;
    }

    /**
     * @param  string[]  $drivers
     */
    private function hasMockDriver(array $drivers): bool
    {
        foreach ($drivers as $driver) {
            if (str_starts_with($driver, 'Mock')) {
                return true;
            }
        }

        return false;
    }
}
