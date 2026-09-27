<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;

/**
 * Katalog › "Üretilecek Ürün Ekle" menü kaydı (reçete + maliyetle geçici stok kartı,
 * /atelier/new-product). Kök, "Tüm Ürünler" (products.index) öğesinin kökü olarak bulunur —
 * etiket değişebildiği için etikete göre aranmaz; bulunamazsa atlanır (Superadmin › Menüler).
 * Menü tek izin taşır: atelier.bom.manage; route ayrıca product.add da ister.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rootId = Menu::query()->where('route_name', 'products.index')->value('parent_id');

        if ($rootId && ! Menu::query()->where('route_name', 'atelier.development-products.create')->exists()) {
            Menu::create([
                'parent_id'  => $rootId,
                'label'      => 'Üretilecek Ürün Ekle',
                'route_name' => 'atelier.development-products.create',
                'url'        => '/atelier/new-product',
                'permission' => 'atelier.bom.manage',
                'sort_order' => (int) Menu::query()->where('parent_id', $rootId)->max('sort_order') + 1,
                'is_active'  => true,
            ]);
        }
    }

    public function down(): void
    {
        Menu::query()->where('route_name', 'atelier.development-products.create')->delete();
    }
};
