<?php

namespace App\Support;

use App\Models\User;

/**
 * Modüllerin tek ayar sayfaları. Her modül kendi ServiceProvider'ında bir kez kaydeder;
 * kabuk (OzModuleTabs) sayfa yetkisi olan kullanıcıya, modülün menü gruplarının sekme
 * satırının sağında bir çark ikonu gösterir. Modül ayarları süper admin'e toplanmaz —
 * her modül kendi sayfasında, sekmeler hâlinde, kendi yetkileriyle yönetir.
 *
 *   app(ModuleSettingsRegistry::class)->register(
 *       key: 'product',
 *       url: '/products/settings',
 *       permission: 'product-settings.view',
 *       menuRoots: ['/products', '/products/stocks', '/orders'],
 *       tabPermissions: ['variant-option.view'],
 *   );
 *
 * menuRoots: çarkın görüneceği kök menülerin (ya da alt menülerinden birinin) URL'leri.
 * tabPermissions: sekmelerin görme yetkileri. Sayfa yetkisi olsa bile bunlardan hiçbiri
 * yoksa sayfa yokmuş gibi davranılır — çark gösterilmez, sayfa 403 döner (canAccess()).
 * Modüle yeni ayar sekmesi eklenince yetkisi buraya da eklenir.
 */
class ModuleSettingsRegistry
{
    /** @var array<string, array{key: string, url: string, permission: string, menuRoots: list<string>, tabPermissions: list<string>}> */
    private array $entries = [];

    /**
     * @param  list<string>  $menuRoots
     * @param  list<string>  $tabPermissions
     */
    public function register(string $key, string $url, string $permission, array $menuRoots, array $tabPermissions = []): void
    {
        $this->entries[$key] = compact('key', 'url', 'permission', 'menuRoots', 'tabPermissions');
    }

    /** Sayfa yetkisi + (tanımlıysa) en az bir sekme yetkisi. Kayıtsız anahtar → false. */
    public function canAccess(?User $user, string $key): bool
    {
        $entry = $this->entries[$key] ?? null;

        return $user !== null && $entry !== null && $this->allows($user, $entry);
    }

    /** @return list<array{key: string, url: string, menuRoots: list<string>}> */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $visible = [];
        foreach ($this->entries as $entry) {
            if ($this->allows($user, $entry)) {
                $visible[] = ['key' => $entry['key'], 'url' => $entry['url'], 'menuRoots' => $entry['menuRoots']];
            }
        }

        return $visible;
    }

    /** @param  array{permission: string, tabPermissions: list<string>}  $entry */
    private function allows(User $user, array $entry): bool
    {
        if (! $user->can($entry['permission'])) {
            return false;
        }

        return $entry['tabPermissions'] === [] || $user->canAny($entry['tabPermissions']);
    }
}
