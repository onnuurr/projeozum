<?php

namespace Modules\Bagisto\Console;

use Illuminate\Console\Command;
use Modules\Bagisto\Jobs\PushCategoryToBagisto;
use Modules\Product\Models\Category;

/**
 * Kategori senkronu (2026-09-26) devreye alınmadan önce oluşturulan kategorileri Bagisto'ya
 * gönderir (backfill). Üst kategoriler önce dizilir; Bagisto alt kategoriyi üstünün altına açabilsin.
 */
class SyncCategoriesCommand extends Command
{
    protected $signature = 'bagisto:sync-categories';

    protected $description = 'Mevcut tüm kategorileri Bagisto\'ya push etmek için kuyruğa diziler (backfill).';

    public function handle(): int
    {
        $categories = Category::query()->orderByRaw('parent_id is not null')->orderBy('parent_id')->orderBy('id')->get(['id']);

        foreach ($categories as $category) {
            PushCategoryToBagisto::dispatch($category->id);
        }

        $this->info("{$categories->count()} kategori Bagisto senkron kuyruğuna dizildi.");

        return self::SUCCESS;
    }
}
