<?php

namespace ArchitectureDoctor\Rules\Migrations;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;
use Symfony\Component\Finder\Finder;

/**
 * CLAUDE.md "Yeni soft-delete'li model eklerken Prunable değerlendir" maddesini denetler.
 * Sadece log/hareket niteliğindeki isim desenleriyle eşleşen modellere bakar (ör.
 * StockMovement) — düşük hacimli iş varlıkları (ör. Tenant) EXCLUDED_MODELS ile bilerek
 * susturulur (SchemaAuditCommand::WHITELIST ile aynı desen).
 */
final class SoftDeleteShouldBePrunableRule implements ArchitectureRule
{
    private const EXCLUDED_MODELS = [
        'Tenant', 'Carrier', 'Product', 'Warehouse', 'Category', 'BankAccount',
        'SupplierInvoice', 'DesignCard', 'ProductionOrder', 'Material', 'User',
    ];

    private const NAME_PATTERN = '/(Movement|History|Log|TimelineEntry|Run)$/';

    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'migration.soft-delete-should-be-prunable';
    }

    public function category(): string
    {
        return 'Migrations';
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
        $paths = $this->paths ?? $this->defaultModelPaths();

        if ($paths === []) {
            return [];
        }

        $findings = [];

        foreach (Finder::create()->files()->name('*.php')->in($paths) as $file) {
            $className = $file->getFilenameWithoutExtension();

            if (in_array($className, self::EXCLUDED_MODELS, true)) {
                continue;
            }

            if (! preg_match(self::NAME_PATTERN, $className)) {
                continue;
            }

            $content = $file->getContents();

            if ($this->usesTrait($content, 'SoftDeletes') && ! $this->usesTrait($content, 'Prunable')) {
                $findings[] = new Finding(
                    message: "{$className} SoftDeletes kullanıyor ama Prunable kullanmıyor — büyüyen bir log/hareket tablosu birikebilir.",
                    file: MigrationFileScanner::relativePath($file->getPathname()),
                    suggestion: "Illuminate\\Database\\Eloquent\\Prunable ekleyip prunable() eşiği tanımlayın (örnek: Modules/Product/Models/StockMovement.php), ya da düşük hacimliyse EXCLUDED_MODELS'e ekleyin.",
                );
            }
        }

        return $findings;
    }

    /**
     * @return string[]
     */
    private function defaultModelPaths(): array
    {
        return array_values(array_filter(
            array_merge(
                [app_path('Models')],
                glob(base_path('Modules/*/Models'), GLOB_ONLYDIR) ?: [],
            ),
            'is_dir',
        ));
    }

    /**
     * `use Illuminate\...\SoftDeletes;` (import) ile `use SoftDeletes;` (trait) ayrımını,
     * satırda backslash olup olmamasına bakarak yapar — import satırları her zaman FQCN.
     */
    private function usesTrait(string $content, string $trait): bool
    {
        return (bool) preg_match(
            '/^\s*use\s+(?!.*\\\\)[\w,\s]*\b'.preg_quote($trait, '/').'\b[\w,\s]*;/m',
            $content,
        );
    }
}
