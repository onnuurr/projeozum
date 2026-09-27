<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductService;

/**
 * "Üretilecek Ürün": reçeteden ön maliyet hesaplayıp geçici stok kartı açar.
 *
 * Geçici kart ayrı bir tablo değil, status=development / sourcing=manufactured bir
 * Product'tır — reçete (product_boms) ve iş emri (production_orders) product/varyant
 * id'lerine bağlı olduğu için dönüşümde veri taşınmaz, yalnız durum değişir. Dönüşüm:
 * ProductionOrderService::complete() → ProductService::promoteFromDevelopment().
 */
class DevelopmentProductService
{
    public function __construct(
        private ProductService $products,
        private BomService $boms,
    ) {}

    /**
     * @param  array<string, mixed>  $data  StoreDevelopmentProductRequest::validated()
     */
    public function create(array $data): Product
    {
        $extra = (float) ($data['extra_cost_per_unit'] ?? 0);
        $cost  = $this->boms->unitCostFor($data['lines'], $extra);
        $price = (float) $data['price'];

        return DB::transaction(function () use ($data, $extra, $cost, $price) {
            $product = $this->products->create(
                [
                    'name'           => $data['name'],
                    'sku'            => $data['sku'],
                    'category_id'    => $data['category_id'],
                    'brand_id'       => $data['brand_id'] ?? null,
                    'gender'         => $data['gender'],
                    // Ön maliyet; üretim tamamlanınca gerçek birim maliyetle ezilir.
                    'purchase_price' => $cost['unit_cost'],
                    'variants'       => array_map(fn (array $v) => [
                        'sku'        => $v['sku'],
                        'size'       => $v['size'] ?? null,
                        'color_name' => $v['color_name'] ?? null,
                        'color_hex'  => $v['color_hex'] ?? null,
                        'price'      => $price,
                        'stock'      => 0,
                    ], $data['variants']),
                ],
                [],
                Product::STATUS_DEVELOPMENT,
                Product::SOURCING_MANUFACTURED,
            );

            $this->boms->replaceActive(
                $product->id,
                $data['bom_name'] ?? 'Varsayılan Reçete',
                $data['lines'],
                $extra,
            );

            return $product;
        });
    }
}
