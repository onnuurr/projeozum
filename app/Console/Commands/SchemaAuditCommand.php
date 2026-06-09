<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Salt-okuma şema denetimi: canlı DB şemasını koddaki modellerle karşılaştırıp
 * artık karşılığı olmayan (orphan) tablo/kolonları ve tablo şişme metriklerini raporlar.
 *
 * Hiçbir şey SİLMEZ — yalnızca teşhis üretir. Çıktı: storage/app/schema-audit.json
 */
class SchemaAuditCommand extends Command
{
    protected $signature = 'schema:audit {--json : Sadece JSON dosyasını üret, konsol özetini atla}';

    protected $description = 'DB şemasını modellerle karşılaştırıp orphan tablo/kolonları ve şişme metriklerini raporlar (salt-okuma)';

    /**
     * Yanlış pozitif üretmemesi için orphan taramasından muaf tutulan tablolar:
     * framework, pivot ve paket (spatie/permission, nwidart) tabloları.
     */
    private const WHITELIST = [
        // Framework
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'sessions', 'password_reset_tokens', 'password_resets',
        // spatie/permission
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        // Modellerle 1:1 eşleşmeyen pivot tablolar
        'category_marketplace_mappings', 'tenant_product_access', 'tenant_access_rules',
    ];

    public function handle(): int
    {
        $modelMap   = $this->buildModelMap();
        $dbTables   = Schema::getTableListing();
        $modelTables = array_keys($modelMap);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'connection'   => DB::getDefaultConnection(),
            'tables'       => [],
            'orphan_tables' => [],
            'summary'      => [],
        ];

        foreach ($dbTables as $table) {
            // PostgreSQL şema öneki (public.) gelebilir; model/whitelist eşleşmesi için çıplak ad kullan.
            $bare     = Str::afterLast($table, '.');
            $columns  = Schema::getColumnListing($table);
            $rowCount = (int) DB::table($table)->count();

            $hasModel    = isset($modelMap[$bare]);
            $isWhitelist = in_array($bare, self::WHITELIST, true);
            $isOrphan    = ! $hasModel && ! $isWhitelist;

            $softDeleted = null;
            if (in_array('deleted_at', $columns, true)) {
                $softDeleted = (int) DB::table($table)->whereNotNull('deleted_at')->count();
            }

            $entry = [
                'table'              => $table,
                'rows'               => $rowCount,
                'soft_deleted_rows'  => $softDeleted,
                'columns'            => $columns,
                'mapped_model'       => $modelMap[$bare]['class'] ?? null,
                'is_orphan_table'    => $isOrphan,
            ];

            // Kolon seviyesi: modeli olan tablolarda fillable/cast/bilinen kolonlarda
            // görünmeyen kolonlar — "gürültülü, elle gözden geçir" olarak işaretlenir.
            if ($hasModel) {
                $known = array_merge(
                    $modelMap[$bare]['known_columns'],
                    ['id', 'created_at', 'updated_at', 'deleted_at']
                );
                $suspectCols = array_values(array_filter($columns, function ($col) use ($known) {
                    // *_id foreign key'ler ve bilinen kolonlar muaf
                    return ! in_array($col, $known, true) && ! Str::endsWith($col, '_id');
                }));
                $entry['suspect_columns'] = $suspectCols;
            }

            $report['tables'][] = $entry;

            if ($isOrphan) {
                $report['orphan_tables'][] = ['table' => $table, 'rows' => $rowCount];
            }
        }

        $report['summary'] = [
            'db_table_count'       => count($dbTables),
            'model_count'          => count($modelMap),
            'orphan_table_count'   => count($report['orphan_tables']),
            'total_rows'           => array_sum(array_column($report['tables'], 'rows')),
            'total_soft_deleted'   => array_sum(array_map(fn ($t) => $t['soft_deleted_rows'] ?? 0, $report['tables'])),
        ];

        $path = storage_path('app/schema-audit.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! $this->option('json')) {
            $this->renderConsole($report);
        }

        $this->info("Tam rapor: {$path}");

        return self::SUCCESS;
    }

    /**
     * Modules/<X>/Models ve app/Models altındaki Eloquent modellerini bulup
     * tablo adı + bilinen kolonlarını (fillable ∪ casts anahtarları) reflection ile çıkarır.
     *
     * @return array<string, array{class:string, known_columns:array<int,string>}>
     */
    private function buildModelMap(): array
    {
        $map   = [];
        $files = array_merge(
            File::isDirectory(app_path('Models')) ? File::allFiles(app_path('Models')) : [],
            File::isDirectory(base_path('Modules')) ? File::glob(base_path('Modules/*/Models/*.php')) : []
        );

        // glob string döndürür, allFiles SplFileInfo — ikisini de path'e indir
        $paths = array_map(fn ($f) => is_string($f) ? $f : $f->getPathname(), $files);

        foreach ($paths as $path) {
            $class = $this->classFromPath($path);
            if ($class === null || ! class_exists($class)) {
                continue;
            }
            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                $instance = new $class();
            } catch (\Throwable) {
                continue;
            }

            $table   = $instance->getTable();
            $fillable = $instance->getFillable();
            $casts    = array_keys($instance->getCasts());

            $map[$table] = [
                'class'         => $class,
                'known_columns' => array_values(array_unique(array_merge($fillable, $casts))),
            ];
        }

        return $map;
    }

    /**
     * Dosya yolundaki namespace + sınıf adından FQCN üretir.
     */
    private function classFromPath(string $path): ?string
    {
        $contents = File::get($path);

        if (! preg_match('/namespace\s+([^;]+);/', $contents, $ns)) {
            return null;
        }
        if (! preg_match('/class\s+(\w+)/', $contents, $cls)) {
            return null;
        }

        return trim($ns[1]) . '\\' . $cls[1];
    }

    private function renderConsole(array $report): void
    {
        $this->newLine();
        $this->components->info('Şema Denetimi Özeti');
        $s = $report['summary'];
        $this->line("  Tablo sayısı (DB): {$s['db_table_count']}  |  Model: {$s['model_count']}");
        $this->line("  Toplam satır: {$s['total_rows']}  |  Soft-delete kalıntısı: {$s['total_soft_deleted']}");
        $this->newLine();

        if (! empty($report['orphan_tables'])) {
            $this->components->warn('Şüpheli ORPHAN tablolar (kodda model karşılığı yok):');
            $this->table(
                ['Tablo', 'Satır'],
                array_map(fn ($t) => [$t['table'], $t['rows']], $report['orphan_tables'])
            );
            $this->components->warn('Bu listeyi ELLE doğrulayın; DROP yalnızca onay sonrası migration ile yapılır.');
        } else {
            $this->components->info('Şüpheli orphan tablo bulunamadı.');
        }

        // En çok soft-delete kalıntısı barındıran ilk 10 tablo
        $bloated = collect($report['tables'])
            ->filter(fn ($t) => ($t['soft_deleted_rows'] ?? 0) > 0)
            ->sortByDesc('soft_deleted_rows')
            ->take(10)
            ->values();

        if ($bloated->isNotEmpty()) {
            $this->newLine();
            $this->components->warn('En çok soft-delete kalıntısı (prune adayı):');
            $this->table(
                ['Tablo', 'Soft-delete satır', 'Toplam satır'],
                $bloated->map(fn ($t) => [$t['table'], $t['soft_deleted_rows'], $t['rows']])->all()
            );
        }
    }
}
