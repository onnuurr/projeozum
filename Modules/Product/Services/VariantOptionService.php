<?php

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductColor;
use Modules\Product\Models\ProductSize;
use Modules\Product\Models\ProductVariant;

/**
 * Ürün Ayarları'ndaki beden/renk seçeneklerinin yazma işlemleri.
 *
 * Varyantlar beden/rengi düz metin tutar (product_variants.size / color_name / color_hex). Bir
 * seçenek yeniden adlandırılınca (ya da rengin hex'i değişince) o değeri kullanan varyantlar da
 * güncellenir ve etkilenen ürünler için ProductUpdated yayınlanır — aksi halde seçenek listesi ile
 * varyantlar (ve onları tüketen mağaza/pazaryeri senkronları) ayrışırdı. Silme varyantlara dokunmaz.
 *
 * Model olayları (created/updated/deleted) mağaza senkronunu tetikler (BagistoServiceProvider);
 * bu yüzden toplu silme de tek tek model üzerinden yapılır.
 */
class VariantOptionService
{
    public function createSize(array $data): ProductSize
    {
        return ProductSize::create($this->withSortOrder($data, ProductSize::class));
    }

    /**
     * Ürün Ayarları'ndaki virgülle toplu ekleme: girilen sırayla listenin sonuna eklenir.
     *
     * @param  'sizes'|'colors'  $type
     * @param  array<int, array{name: string, hex?: string}>  $items
     */
    public function bulkCreate(string $type, array $items): int
    {
        $model = $type === 'colors' ? ProductColor::class : ProductSize::class;

        return DB::transaction(function () use ($model, $items) {
            foreach ($items as $item) {
                $model::create($this->withSortOrder($item, $model));
            }

            return count($items);
        });
    }

    public function updateSize(ProductSize $size, array $data): ProductSize
    {
        $oldName = $size->name;

        $productIds = DB::transaction(function () use ($size, $data, $oldName) {
            $size->update($this->withSortOrder($data, ProductSize::class, $size->sort_order));

            if ($size->name === $oldName) {
                return [];
            }

            return $this->updateVariants(['size' => $oldName], ['size' => $size->name]);
        });

        $this->dispatchProductUpdated($productIds);

        return $size;
    }

    public function createColor(array $data): ProductColor
    {
        return ProductColor::create($this->withSortOrder($data, ProductColor::class));
    }

    public function updateColor(ProductColor $color, array $data): ProductColor
    {
        $oldName = $color->name;

        $productIds = DB::transaction(function () use ($color, $data, $oldName) {
            $color->update($this->withSortOrder($data, ProductColor::class, $color->sort_order));

            if (! $color->wasChanged(['name', 'hex'])) {
                return [];
            }

            return $this->updateVariants(
                ['color_name' => $oldName],
                ['color_name' => $color->name, 'color_hex' => $color->hex],
            );
        });

        $this->dispatchProductUpdated($productIds);

        return $color;
    }

    public function delete(ProductSize|ProductColor $option): void
    {
        $option->delete();
    }

    /**
     * @param  'sizes'|'colors'  $type
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(string $type, array $ids): int
    {
        $model = $type === 'colors' ? ProductColor::class : ProductSize::class;

        return DB::transaction(function () use ($model, $ids) {
            $options = $model::whereIn('id', $ids)->get();
            $options->each->delete();

            return $options->count();
        });
    }

    /**
     * Seçenek adı → onu kullanan varyant sayısı (Ürün Ayarları listesindeki "Varyant" sütunu).
     *
     * @param  'size'|'color_name'  $column
     * @return Collection<string, int>
     */
    public function variantUsage(string $column): Collection
    {
        return ProductVariant::query()
            ->whereNotNull($column)
            ->selectRaw("{$column}, count(*) as total")
            ->groupBy($column)
            ->pluck('total', $column);
    }

    /**
     * Eşleşen varyantları günceller; etkilenen ürün id'lerini döner.
     *
     * @return array<int, int>
     */
    private function updateVariants(array $where, array $values): array
    {
        $query = ProductVariant::query()->where($where);
        $productIds = (clone $query)->distinct()->pluck('product_id')->all();

        if ($productIds !== []) {
            // Toplu güncelleme: varyant model olayları/SKU mantığı tetiklenmez — yalnız etiket değişiyor.
            $query->update($values);
        }

        return $productIds;
    }

    /** Transaction commit'inden SONRA çağrılır (rollback olursa event hiç ateşlenmez). */
    private function dispatchProductUpdated(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        Product::whereIn('id', $productIds)->each(fn (Product $product) => ProductUpdated::dispatch($product));
    }

    /**
     * Sıra boş bırakılırsa: yeni kayıt listenin sonuna, düzenlenen kayıt mevcut sırasında kalır.
     *
     * @param  class-string<Model>  $model
     */
    private function withSortOrder(array $data, string $model, ?int $current = null): array
    {
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = $current ?? ((int) $model::max('sort_order') + 1);
        }

        return $data;
    }
}
