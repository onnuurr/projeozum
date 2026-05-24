<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $brands = Brand::pluck('id', 'slug');
        $cats   = Category::pluck('id', 'slug');

        if ($brands->isEmpty() || $cats->isEmpty()) {
            $this->command?->warn('Brand veya Category boş — önce BrandSeeder ve CategorySeeder çalıştırılmalı.');
            return;
        }

        $palette = [
            ['name' => 'Siyah',     'hex' => '#111111'],
            ['name' => 'Beyaz',     'hex' => '#fafafa'],
            ['name' => 'Lacivert',  'hex' => '#1e3a8a'],
            ['name' => 'Gri',       'hex' => '#6b7280'],
            ['name' => 'Bej',       'hex' => '#d6c7a8'],
            ['name' => 'Bordo',     'hex' => '#7c1f1f'],
            ['name' => 'Yeşil',     'hex' => '#15803d'],
            ['name' => 'Mavi',      'hex' => '#2563eb'],
        ];

        $tshirtSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $pantsSizes  = ['28', '30', '32', '34', '36', '38'];
        $shoeSizes   = ['38', '39', '40', '41', '42', '43', '44'];

        $samples = [
            ['name' => 'Basic Crew Tişört',     'cat' => 'tisort',   'brand' => 'lc-waikiki', 'gender' => 'Erkek',  'price' => 199.90,  'old' => 249.90, 'rating' => 4.4, 'reviews' => 312, 'new' => true,  'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Oversize Tişört',        'cat' => 'tisort',   'brand' => 'koton',      'gender' => 'Unisex', 'price' => 249.00,  'old' => null,   'rating' => 4.2, 'reviews' => 145, 'new' => false, 'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Logo Tişört',            'cat' => 'tisort',   'brand' => 'nike',       'gender' => 'Erkek',  'price' => 599.00,  'old' => 749.00, 'rating' => 4.7, 'reviews' => 530, 'new' => true,  'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Spor Tişört',            'cat' => 'tisort',   'brand' => 'adidas',     'gender' => 'Kadın',  'price' => 549.00,  'old' => null,   'rating' => 4.3, 'reviews' => 96,  'new' => false, 'ship' => false, 'sizes' => $tshirtSizes],

            ['name' => 'Slim Fit Gömlek',        'cat' => 'gomlek',   'brand' => 'mavi',       'gender' => 'Erkek',  'price' => 749.90,  'old' => 899.00, 'rating' => 4.5, 'reviews' => 210, 'new' => false, 'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Oxford Gömlek',          'cat' => 'gomlek',   'brand' => 'zara',       'gender' => 'Erkek',  'price' => 1199.00, 'old' => null,   'rating' => 4.6, 'reviews' => 87,  'new' => true,  'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Saten Gömlek',           'cat' => 'gomlek',   'brand' => 'koton',      'gender' => 'Kadın',  'price' => 549.00,  'old' => 699.00, 'rating' => 4.1, 'reviews' => 56,  'new' => false, 'ship' => false, 'sizes' => $tshirtSizes],

            ['name' => 'Klasik Chino Pantolon',  'cat' => 'pantolon', 'brand' => 'lc-waikiki', 'gender' => 'Erkek',  'price' => 699.00,  'old' => null,   'rating' => 4.2, 'reviews' => 178, 'new' => false, 'ship' => true,  'sizes' => $pantsSizes],
            ['name' => '501 Original Jean',      'cat' => 'pantolon', 'brand' => 'levis',      'gender' => 'Erkek',  'price' => 2499.00, 'old' => 2999.00,'rating' => 4.8, 'reviews' => 1240,'new' => true,  'ship' => true,  'sizes' => $pantsSizes],
            ['name' => 'Mom Jean',               'cat' => 'pantolon', 'brand' => 'mavi',       'gender' => 'Kadın',  'price' => 1199.00, 'old' => null,   'rating' => 4.5, 'reviews' => 320, 'new' => false, 'ship' => true,  'sizes' => $pantsSizes],
            ['name' => 'Eşofman Altı',           'cat' => 'pantolon', 'brand' => 'puma',       'gender' => 'Unisex', 'price' => 899.00,  'old' => 1099.00,'rating' => 4.4, 'reviews' => 145, 'new' => true,  'ship' => false, 'sizes' => $tshirtSizes],

            ['name' => 'Yazlık Şifon Elbise',    'cat' => 'elbise',   'brand' => 'zara',       'gender' => 'Kadın',  'price' => 1499.00, 'old' => null,   'rating' => 4.6, 'reviews' => 92,  'new' => true,  'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Triko Elbise',           'cat' => 'elbise',   'brand' => 'koton',      'gender' => 'Kadın',  'price' => 799.00,  'old' => 999.00, 'rating' => 4.3, 'reviews' => 64,  'new' => false, 'ship' => true,  'sizes' => $tshirtSizes],

            ['name' => 'Şişme Mont',             'cat' => 'mont',     'brand' => 'nike',       'gender' => 'Unisex', 'price' => 3499.00, 'old' => 3999.00,'rating' => 4.7, 'reviews' => 88,  'new' => false, 'ship' => true,  'sizes' => $tshirtSizes],
            ['name' => 'Trençkot',               'cat' => 'mont',     'brand' => 'zara',       'gender' => 'Kadın',  'price' => 2799.00, 'old' => null,   'rating' => 4.5, 'reviews' => 41,  'new' => true,  'ship' => false, 'sizes' => $tshirtSizes],

            ['name' => 'Air Max 90',             'cat' => 'ayakkabi', 'brand' => 'nike',       'gender' => 'Unisex', 'price' => 4299.00, 'old' => 4999.00,'rating' => 4.8, 'reviews' => 2100,'new' => true,  'ship' => true,  'sizes' => $shoeSizes],
            ['name' => 'Stan Smith',             'cat' => 'ayakkabi', 'brand' => 'adidas',     'gender' => 'Unisex', 'price' => 3499.00, 'old' => null,   'rating' => 4.6, 'reviews' => 980, 'new' => false, 'ship' => true,  'sizes' => $shoeSizes],
            ['name' => 'Suede Klasik',           'cat' => 'ayakkabi', 'brand' => 'puma',       'gender' => 'Erkek',  'price' => 2199.00, 'old' => 2599.00,'rating' => 4.4, 'reviews' => 410, 'new' => false, 'ship' => false, 'sizes' => $shoeSizes],
        ];

        foreach ($samples as $i => $row) {
            $sku = sprintf('TEK-%s-%03d', strtoupper(substr($row['brand'], 0, 3)), $i + 1);
            $brandFrag = strtoupper(substr($row['brand'], 0, 3));

            $product = Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id'   => $cats[$row['cat']]   ?? null,
                    'brand_id'      => $brands[$row['brand']] ?? null,
                    'name'          => $row['name'],
                    'gender'        => $row['gender'],
                    'price'         => $row['price'],
                    'old_price'     => $row['old'],
                    'rating'        => $row['rating'],
                    'review_count'  => $row['reviews'],
                    'is_new'        => $row['new'],
                    'free_shipping' => $row['ship'],
                ],
            );

            $product->variants()->delete();

            $colorPicks = collect($palette)->shuffle()->take(random_int(2, 4))->values();

            $order = 0;
            foreach ($colorPicks as $cIdx => $color) {
                foreach ($row['sizes'] as $sIdx => $size) {
                    // Bazı kombinasyonlar tükenmiş simülasyonu
                    $stock = random_int(0, 30);
                    // Bazı renklerde küçük fiyat farkı
                    $priceDelta = $cIdx === 0 ? 0 : random_int(-20, 50);

                    $product->variants()->create([
                        'size'       => $size,
                        'color_name' => $color['name'],
                        'color_hex'  => $color['hex'],
                        'sku'        => sprintf('%s-%s-%s', $sku, mb_substr($color['name'], 0, 3), $size),
                        'price'      => max(0, $row['price'] + $priceDelta),
                        'old_price'  => $row['old'] !== null ? max(0, $row['old'] + $priceDelta) : null,
                        'stock'      => $stock,
                        'sort_order' => $order++,
                    ]);
                }
            }

            // Base ürün price/old_price'i, en düşük varyant fiyatına eşitle
            $minPrice    = $product->variants()->min('price');
            $minOldPrice = $product->variants()->whereNotNull('old_price')->min('old_price');
            $product->update([
                'price'     => $minPrice ?? $row['price'],
                'old_price' => $minOldPrice,
            ]);
        }
    }
}
