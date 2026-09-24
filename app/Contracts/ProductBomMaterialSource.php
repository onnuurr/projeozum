<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

/**
 * Bir ürünün üretim reçetesindeki (BOM) materyaller. Product modülü Atelier'e bağımlı
 * olamadığı için (Modules/Product/module.json forbidden_dependencies) reçeteye bu kernel
 * sözleşmesi üzerinden erişir; tek sürücü Atelier'in
 * Modules\Atelier\Services\ProductBomMaterialSource'u (AtelierServiceProvider::register()).
 * Atelier yüklü değilse binding yoktur ve Product reçete adımını atlar.
 *
 * Neden: 2026-09-22'de mimari ihlali kapatmak için Product::boms() silindi, ama
 * ProductMaterialLinkService::resolve() onu kullanıyordu → ürün detay sayfası ve AI
 * açıklama üretimi 500 veriyordu (bkz. ProductMaterialLinkTest).
 */
interface ProductBomMaterialSource
{
    /**
     * Ürünün aktif reçetesinin (yoksa ilk reçetesinin) satırlarındaki materyaller, satır
     * sırasıyla. Reçete yoksa boş koleksiyon.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function materialsFor(int $productId): Collection;
}
