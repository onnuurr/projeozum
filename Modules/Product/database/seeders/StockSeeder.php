<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::where('code', 'ANA01')->first()
            ?? Warehouse::where('is_active', true)->orderBy('id')->first();

        if (! $warehouse) {
            $this->command?->warn('Aktif depo bulunamadı, StockSeeder atlandı.');
            return;
        }

        $this->command?->info("StockSeeder: {$warehouse->code} ({$warehouse->name}) deposu kullanılıyor.");

        $variants = ProductVariant::query()
            ->orderBy('id')
            ->take(50)
            ->get(['id']);

        if ($variants->isEmpty()) {
            $this->command?->warn('Hiç varyant yok, StockSeeder atlandı.');
            return;
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($variants, $warehouse, &$created, &$skipped) {
            foreach ($variants as $index => $variant) {
                $exists = Stock::where('product_variant_id', $variant->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // 7 sağlıklı : 2 kritik : 1 sıfır karışım
                $bucket = $index % 10;
                if ($bucket < 7) {
                    $quantity     = random_int(20, 100);
                    $minQuantity  = 5;
                } elseif ($bucket < 9) {
                    $quantity     = random_int(1, 5);
                    $minQuantity  = 10;
                } else {
                    $quantity     = 0;
                    $minQuantity  = 5;
                }

                Stock::create([
                    'product_variant_id' => $variant->id,
                    'warehouse_id'       => $warehouse->id,
                    'quantity'           => $quantity,
                    'reserved_quantity'  => 0,
                    'min_quantity'       => $minQuantity,
                ]);

                if ($quantity > 0) {
                    StockMovement::create([
                        'product_variant_id' => $variant->id,
                        'warehouse_id'       => $warehouse->id,
                        'type'               => StockMovement::TYPE_IN,
                        'quantity'           => $quantity,
                        'before_quantity'    => 0,
                        'after_quantity'     => $quantity,
                        'note'               => 'Seeder ile açılış stoğu',
                        'user_id'            => null,
                    ]);
                }

                ProductVariant::where('id', $variant->id)->update([
                    'stock' => (int) Stock::where('product_variant_id', $variant->id)->sum('quantity'),
                ]);

                $created++;
            }
        });

        $this->command?->info("StockSeeder: {$created} kayıt eklendi, {$skipped} atlandı.");
    }
}
