<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Beden/renk sıraları 10, 20, 30… olarak tohumlanmıştı; projedeki diğer sıralı listeler
 * (kategoriler: Category::nextSortOrder, categories:renumber) 1, 2, 3… kullanıyor.
 * Mevcut görünen sıra korunarak (sort_order, name, id) 1'den yeniden numaralanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renumber(fn (int $i) => $i + 1);
    }

    public function down(): void
    {
        $this->renumber(fn (int $i) => ($i + 1) * 10);
    }

    private function renumber(callable $order): void
    {
        foreach (['product_sizes', 'product_colors'] as $table) {
            $ids = DB::table($table)->orderBy('sort_order')->orderBy('name')->orderBy('id')->pluck('id');
            foreach ($ids as $i => $id) {
                DB::table($table)->where('id', $id)->update(['sort_order' => $order($i)]);
            }
        }
    }
};
