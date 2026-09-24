<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProductPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'product.view'        => 'Ürünleri Görüntüle',
            'product.card.view'   => 'Ürün Kartını Görüntüle (Fiyat Listeleri + Depo Stokları)',
            'product.add'         => 'Ürün Ekle/Düzenle',
            'product.delete'      => 'Ürün Sil',
            'product.ai.generate' => 'Ürün AI Açıklama Üret',
            'category.view'       => 'Kategorileri Görüntüle',
            'category.manage'     => 'Kategori & Pazaryeri Eşleme Yönet',
            'category.create'     => 'Kategori Ekle (Hızlı)',
            'brand.view'          => 'Markaları Görüntüle',
            'brand.manage'        => 'Marka Yönet',
            'warehouse.view'      => 'Depoları Görüntüle',
            'warehouse.manage'    => 'Depo Yönet',
            'stock.manage'        => 'Stok Yönet',
            'price-list.manage'   => 'Fiyat Listesi Yönet',
            'order.view'          => 'Siparişleri Görüntüle',
            'order.manage'        => 'Sipariş Durumu Yönet',
            'carrier.manage'      => 'Kargo Firmalarını Yönet',
        ];

        $created = [];
        foreach ($permissions as $name => $displayName) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            // Mevcut bir permission ise display_name'i güncelle.
            if (! $perm->wasRecentlyCreated && $perm->display_name !== $displayName) {
                $perm->update(['display_name' => $displayName]);
            }
            $created[] = $perm->name;
        }

        // Superadmin rolüne hepsini ata.
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }

        // Yönetici rolü (Roller ekranından oluşturulmuş iç rol) Ürün Kartı'nı da görür.
        $yonetici = Role::where('name', 'yonetici')->where('guard_name', 'web')->first();
        if ($yonetici) {
            $yonetici->givePermissionTo(['product.view', 'product.card.view', 'category.view', 'brand.view', 'warehouse.view']);
        }

        // Tenant rolü paylaşımlı katalogu yalnızca görüntüler (yönetim izinleri verilmez).
        // Elle verilmiş olabilecek diğer Product izinleri geri alınır (canlıda product.delete
        // drift'i bulunmuştu, 2026-09-24).
        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        if ($tenantRole) {
            $tenantRole->givePermissionTo('product.view');
            foreach (array_diff($created, ['product.view']) as $name) {
                if ($tenantRole->hasPermissionTo($name)) {
                    $tenantRole->revokePermissionTo($name);
                }
            }
        }
    }
}
