<?php

namespace Modules\Product\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Category;

/**
 * Kategori sıra numaralarını her üst kategorinin altında 1, 2, 3… olarak yeniden dağıtır
 * (mevcut görünen sıra korunur: sort_order, ad, id). Sıralar bozulduğunda / hepsi 0 kaldığında
 * elle çalıştırılan onarım aracı — veri onarımı migration'a konmaz.
 */
class RenumberCategoriesCommand extends Command
{
    protected $signature = 'categories:renumber {--dry-run : Değiştirmeden ne yapılacağını göster}';

    protected $description = 'Kategori sıralarını her üst kategori içinde 1\'den başlayarak yeniden numaralandırır.';

    public function handle(): int
    {
        $changed = 0;
        $dry = (bool) $this->option('dry-run');

        DB::transaction(function () use (&$changed, $dry) {
            Category::query()
                ->orderBy('sort_order')->orderBy('name')->orderBy('id')
                ->get()
                ->groupBy(fn (Category $c) => $c->parent_id ?? 0)
                ->each(function ($siblings) use (&$changed, $dry) {
                    foreach ($siblings->values() as $i => $category) {
                        $order = $i + 1;
                        if ($category->sort_order === $order) {
                            continue;
                        }
                        $this->line(sprintf('  %-30s %d → %d', $category->name, $category->sort_order, $order));
                        if (! $dry) {
                            $category->update(['sort_order' => $order]); // model üzerinden: Bagisto position senkronu
                        }
                        $changed++;
                    }
                });
        });

        $this->info(($dry ? '[deneme] ' : '') . "{$changed} kategorinin sırası " . ($dry ? 'değişecek.' : 'güncellendi.'));

        return self::SUCCESS;
    }
}
