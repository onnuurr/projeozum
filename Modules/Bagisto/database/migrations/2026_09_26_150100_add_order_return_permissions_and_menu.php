<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siparişler › İadeler (bayi iade yönetimi) izinleri ve menü bağlantısı.
 * Para iadesi geri alınamaz olduğu için karar yetkisinden ayrı tutulur (finance.einvoice.send deseni).
 * Menüde route'suz duran "İadeler" öğesi bu sayfaya bağlanır; yoksa oluşturulur.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'order-return.view' => 'İade Taleplerini Görüntüle',
        'order-return.manage' => 'İade Taleplerini Yönet (onay/red, fatura kontrolü)',
        'order-return.refund' => 'İade Ödemesini Gerçekleştir',
    ];

    public function up(): void
    {
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();

        foreach (self::PERMISSIONS as $name => $displayName) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            $superadmin?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $root = Menu::query()->whereNull('parent_id')->where('label', 'Siparişler')->first();
        if (! $root) {
            return;
        }

        $menu = Menu::query()->where('parent_id', $root->id)->where('label', 'İadeler')->first();

        $attributes = [
            'route_name' => 'order-returns.index',
            'url' => '/order-returns',
            'permission' => 'order-return.view',
            'is_active' => true,
        ];

        if ($menu) {
            $menu->update($attributes);
        } elseif (! Menu::query()->where('route_name', 'order-returns.index')->exists()) {
            Menu::create($attributes + [
                'parent_id' => $root->id,
                'label' => 'İadeler',
                'sort_order' => (int) Menu::query()->where('parent_id', $root->id)->max('sort_order') + 1,
            ]);
        }
    }

    public function down(): void
    {
        // "İadeler" öğesi bu migration'dan önce de (route'suz) vardı; silinmez, bağlantısı geri alınır.
        Menu::query()->where('route_name', 'order-returns.index')->update([
            'route_name' => null,
            'url' => null,
            'permission' => null,
            'is_active' => false,
        ]);

        Permission::whereIn('name', array_keys(self::PERMISSIONS))->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
