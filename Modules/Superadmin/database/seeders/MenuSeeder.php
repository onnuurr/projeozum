<?php

namespace Modules\Superadmin\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Superadmin\Models\Menu;

class MenuSeeder extends Seeder
{
    /**
     * Mevcut hardcoded AppLayout menülerini (baseNavItems) DB'ye taşır.
     * Kökler ikonlu (sidebar), child'lar path'li (header). Idempotent:
     * aynı label+parent için tekrar oluşturmaz.
     * Ayrıca superadmin'e özgü nav girişlerini de ekler.
     */
    public function run(): void
    {
        // Standart tenant navigasyonu
        foreach ($this->definition() as $order => $root) {
            $rootMenu = Menu::firstOrCreate(
                ['label' => $root['label'], 'parent_id' => null],
                [
                    'icon'       => $root['icon'],
                    'url'        => $root['url'] ?? null,
                    'permission' => $root['permission'] ?? null,
                    'sort_order' => $order,
                    'is_active'  => true,
                ],
            );

            foreach ($root['children'] as $childOrder => $child) {
                Menu::firstOrCreate(
                    ['label' => $child['label'], 'parent_id' => $rootMenu->id],
                    [
                        'url'        => $child['url'] ?? null,
                        'sort_order' => $childOrder,
                        'is_active'  => true,
                    ],
                );
            }
        }

        // Superadmin'e özgü nav girişleri
        $this->seedSuperadminEntries();

        // Finance modülü (sadece iç kullanım — finance.manage izni gerekir).
        $this->seedFinanceEntries();
    }

    /**
     * Superadmin paneline özgü menü girişlerini ekler (idempotent).
     * route_name ile eşleşen kayıt varsa yeniden oluşturmaz.
     */
    private function seedSuperadminEntries(): void
    {
        Menu::firstOrCreate(
            ['route_name' => 'superadmin.logs'],
            [
                'parent_id'  => null,
                'label'      => 'Sistem Logları',
                'icon'       => 'reports',
                'url'        => null,
                'permission' => 'logs.view',
                'sort_order' => 99,
                'is_active'  => true,
            ],
        );

        // Creative ret ekranındaki "düzeltilmesi gereken alan" seçim maddelerinin
        // yönetimi. Yalnız creative.rejection-reasons.manage izni (superadmin) görür.
        Menu::firstOrCreate(
            ['route_name' => 'creative.rejection-reasons.index'],
            [
                'parent_id'  => null,
                'label'      => 'Ret Seçim Maddeleri',
                'icon'       => 'reports',
                'url'        => null,
                'permission' => 'creative.rejection-reasons.manage',
                'sort_order' => 98,
                'is_active'  => true,
            ],
        );
    }

    /**
     * Finans modülüne özgü menü ağacı (kök + alt sayfalar). Tenant'lara kapalı;
     * her satır `finance.manage` iznine bağlı. route_name ile eşleşen kayıt
     * varsa yeniden oluşturmaz (idempotent).
     */
    private function seedFinanceEntries(): void
    {
        $root = Menu::firstOrCreate(
            ['route_name' => 'finance.dashboard'],
            [
                'parent_id'  => null,
                'label'      => 'Finans',
                'icon'       => 'reports',
                'url'        => null,
                'permission' => 'finance.manage',
                'sort_order' => 6,
                'is_active'  => true,
            ],
        );

        $children = [
            ['route_name' => 'finance.dashboard', 'label' => 'Genel Bakış'],
            ['route_name' => 'finance.product-costs', 'label' => 'Ürün Maliyetleri'],
            ['route_name' => 'finance.sales', 'label' => 'Satış Geçmişi'],
            ['route_name' => 'finance.tenant-purchases', 'label' => 'Bayi Alışverişleri'],
            ['route_name' => 'finance.supplier-invoices.index', 'label' => 'Alınan Faturalar'],
            ['route_name' => 'finance.proformas.index', 'label' => 'Proforma Faturalar'],
            ['route_name' => 'finance.outgoing-invoices.index', 'label' => 'Düzenlenen Faturalar'],
            ['route_name' => 'finance.bank-accounts.index', 'label' => 'Banka'],
        ];

        foreach ($children as $order => $child) {
            Menu::firstOrCreate(
                ['route_name' => $child['route_name'], 'parent_id' => $root->id],
                [
                    'label'      => $child['label'],
                    'permission' => 'finance.manage',
                    'sort_order' => $order,
                    'is_active'  => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definition(): array
    {
        return [
            ['label' => 'Pano', 'icon' => 'dashboard', 'url' => '/tenant/dashboard', 'children' => [
                ['label' => 'Satış & Pazaryeri', 'url' => '/tenant/dashboard'],
                ['label' => 'Kar Marjı Hesaplayıcı', 'url' => '/tenant/margin-calculator'],
                ['label' => 'Stok Analizi'],
            ]],
            ['label' => 'İlişkiler', 'icon' => 'relations', 'url' => '/tenants', 'children' => [
                ['label' => 'Tenant\'lar', 'url' => '/tenants'],
                ['label' => 'Tenant Tipleri', 'url' => '/tenants/types'],
                ['label' => 'Müşteriler'],
                ['label' => 'Tedarikçiler', 'url' => '/suppliers'],
                ['label' => 'Kullanıcılar', 'url' => '/users'],
                ['label' => 'Partnerler'],
            ]],
            ['label' => 'Katalog', 'icon' => 'package', 'url' => '/products', 'children' => [
                ['label' => 'Tüm Ürünler', 'url' => '/products'],
                ['label' => 'Kategoriler', 'url' => '/products/categories'],
                ['label' => 'Markalar', 'url' => '/products/brands'],
                ['label' => 'Koleksiyonlar'],
            ]],
            ['label' => 'Siparişler', 'icon' => 'orders', 'url' => '/orders', 'permission' => 'order.view', 'children' => [
                ['label' => 'Sipariş Listesi', 'url' => '/orders'],
                ['label' => 'Yeni Sipariş'],
                ['label' => 'Teklifler'],
                ['label' => 'İadeler'],
            ]],
            ['label' => 'Stok', 'icon' => 'layers', 'url' => '/products/stocks', 'children' => [
                ['label' => 'Stok Durumu', 'url' => '/products/stocks'],
                ['label' => 'Stok Hareketleri', 'url' => '/products/stocks/history'],
                ['label' => 'Depolar', 'url' => '/products/warehouses'],
                ['label' => 'Sayım'],
            ]],
            ['label' => 'Takvim', 'icon' => 'calendar', 'children' => [
                ['label' => 'Üretim Takvimi'],
                ['label' => 'Toplantılar'],
                ['label' => 'Tatil Günleri'],
            ]],
            ['label' => 'Atölye', 'icon' => 'scissors', 'url' => '/atelier', 'permission' => 'atelier.view', 'children' => [
                ['label' => 'Tüm Modeller', 'url' => '/atelier'],
                ['label' => 'Taslaklar', 'url' => '/atelier?status=draft'],
                ['label' => 'İnceleme Bekleyenler', 'url' => '/atelier?status=in_review'],
                ['label' => 'Onaylanmış', 'url' => '/atelier?status=approved'],
            ]],
            ['label' => 'İş Emirleri', 'icon' => 'workflow', 'url' => '/workflow', 'children' => [
                ['label' => 'Yeni İş Emri', 'url' => '/workflow'],
                ['label' => 'Aktif Emirler', 'url' => '/workflow'],
                ['label' => 'Tamamlanan'],
                ['label' => 'Geciken'],
            ]],
            ['label' => 'Raporlar', 'icon' => 'reports', 'children' => [
                ['label' => 'Üretim Raporu'],
                ['label' => 'Stok Raporu'],
                ['label' => 'Satış Raporu'],
                ['label' => 'Maliyet Analizi'],
            ]],
            ['label' => 'Sevkiyat', 'icon' => 'shipping', 'children' => [
                ['label' => 'Sevkiyat Listesi'],
                ['label' => 'Yeni Sevkiyat'],
                ['label' => 'Kargo Takibi'],
                ['label' => 'Adresler'],
            ]],
        ];
    }
}
