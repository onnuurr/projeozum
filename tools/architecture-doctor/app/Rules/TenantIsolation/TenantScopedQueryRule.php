<?php

namespace ArchitectureDoctor\Rules\TenantIsolation;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;

/**
 * app/Models/Traits/BelongsToTenant.php global scope'u hiçbir modelde kullanılmıyor —
 * gerçek koruma tamamen manuel: katalog modelleri için scopeAccessibleToTenant()/
 * accessibleToTenant() (bkz. Modules/Product/Models/Product.php), sahiplik modelleri
 * için Policy'de tenant_id karşılaştırması (bkz. Modules/Tenant/Policies/OrderPolicy.php).
 * Bu kural o manuel disiplinin unutulduğu yerleri yakalayan ileri-dönük bir regresyon
 * muhafızıdır (RouteAuthorizationGateTest'in route seviyesindeki karşılığı, burada model
 * sorgusu seviyesinde).
 *
 * Severity::Critical: cross-tenant veri sızıntısı gerçek bir güvenlik riski, bu yüzden
 * (birçok diğer Experimental kuralın aksine) Stable'a yükseltildiğinde fiilen bloklaması
 * hedefleniyor.
 *
 * Model listesi kural içinde açık bir sabit dizi — module.json'a taşınması ileride
 * düşünülebilir ama bugün gerek yok.
 */
final class TenantScopedQueryRule implements ArchitectureRule
{
    private const MODELS = ['Product', 'Order', 'ProductVariant', 'Category', 'Brand'];

    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'tenant-isolation.scoped-query';
    }

    public function category(): string
    {
        return 'Tenant Isolation';
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
        $modelPattern = implode('|', self::MODELS);
        $barePattern = '/\b(?:'.$modelPattern.')::(?:all\(\)|query\(\)->get\(\))/';

        foreach (TenantIsolationFileScanner::files($this->paths) as $file) {
            $content = $file->getContents();

            if (! preg_match_all($barePattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $ranges = $this->methodBodyRanges($content);

            foreach ($matches[0] as [$matchedText, $offset]) {
                $scope = $this->enclosingScope($content, $ranges, $offset);

                if ($this->isProtected($scope)) {
                    continue;
                }

                $findings[] = new Finding(
                    message: "Tenant filtresi olmadan bare `{$matchedText}` çağrısı — cross-tenant veri sızıntısı riski.",
                    file: TenantIsolationFileScanner::relativePath($file->getPathname()),
                    suggestion: "accessibleToTenant() scope'unu (bkz. Modules/Product/Models/Product.php) ya da tenant_id karşılaştırmasını (bkz. Modules/Tenant/Policies/OrderPolicy.php) ekleyin.",
                );
            }
        }

        return $findings;
    }

    /**
     * Dosyadaki her metot gövdesinin (parantez-dengeli) karakter aralığını döner —
     * bir eşleşmenin "hangi metodun içinde" olduğunu bulmak için kullanılır.
     *
     * @return array<int, array{start: int, end: int}>
     */
    private function methodBodyRanges(string $content): array
    {
        $ranges = [];
        $offset = 0;

        while (preg_match(
            '/function\s+\w+\s*\([^)]*\)\s*(?::\s*\??[\w|]+\s*)?\{/',
            $content,
            $m,
            PREG_OFFSET_CAPTURE,
            $offset,
        )) {
            $openBrace = $m[0][1] + strlen($m[0][0]) - 1;
            $depth = 0;
            $end = null;

            for ($i = $openBrace, $len = strlen($content); $i < $len; $i++) {
                if ($content[$i] === '{') {
                    $depth++;
                } elseif ($content[$i] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }

            if ($end === null) {
                break;
            }

            $ranges[] = ['start' => $openBrace + 1, 'end' => $end];
            $offset = $end + 1;
        }

        return $ranges;
    }

    /**
     * @param  array<int, array{start: int, end: int}>  $ranges
     */
    private function enclosingScope(string $content, array $ranges, int $offset): string
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['start'] && $offset < $range['end']) {
                return substr($content, $range['start'], $range['end'] - $range['start']);
            }
        }

        return $content;
    }

    private function isProtected(string $scope): bool
    {
        if (str_contains($scope, 'accessibleToTenant(')) {
            return true;
        }

        return (bool) preg_match(
            '/tenant_id\s*(===|!==|==|!=)|->where(?:Column)?\(\s*[\'"]tenant_id[\'"]|whereTenantId\(/',
            $scope,
        );
    }
}
