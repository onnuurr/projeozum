<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * "Yeni route eklendi ama yetki middleware'i eklemeyi unuttuk" hatasına karşı
 * fail-closed muhafız: auth gerektiren her route ya bir can:/permission:/role:
 * kapısına sahip olmalı, ya da ALLOWLIST'te gerekçesiyle açıkça yer almalıdır.
 * Yeni bir route eklenip yetki middleware'i unutulursa bu test kırılır.
 */
class RouteAuthorizationGateTest extends TestCase
{
    /**
     * Route adı (yoksa "METHOD uri") => bilinçli olarak yetkisiz bırakılma gerekçesi.
     */
    private const ALLOWLIST = [
        // Genel hesap işlemleri — girişli her kullanıcı yalnızca kendi hesabı üzerinde işlem yapar.
        'profile.edit' => 'kullanıcı kendi profilini yönetir',
        'profile.update' => 'kullanıcı kendi profilini yönetir',
        'profile.destroy' => 'kullanıcı kendi hesabını siler',
        'password.confirm' => 'Breeze auth akışı',
        'password.update' => 'kullanıcı kendi şifresini değiştirir',
        'verification.notice' => 'Breeze auth akışı',
        'verification.send' => 'Breeze auth akışı',
        'verification.verify' => 'Breeze auth akışı',
        'logout' => 'Breeze auth akışı',
        'dashboard' => 'girişli her kullanıcı için genel karşılama sayfası',
        'notifications.read' => 'kullanıcı kendi bildirimini okur',
        'notifications.read-all' => 'kullanıcı kendi bildirimlerini okur',
        'POST confirm-password' => 'Breeze auth akışı (isimsiz route)',

        // Sepet — kullanıcı yalnızca kendi sepetini değiştirir.
        'cart.add' => 'kullanıcı kendi sepetine ekler',
        'cart.update' => 'kullanıcı kendi sepetini günceller',
        'cart.remove' => 'kullanıcı kendi sepetinden çıkarır',
        'cart.clear' => 'kullanıcı kendi sepetini temizler',

        // Genel katalog görüntüleme — bilinçli "genel erişim" (bkz. laravel-authorization skill).
        'products.index' => 'genel katalog görüntüleme, yetki gerekmiyor',
        'products.show' => 'genel katalog görüntüleme, yetki gerekmiyor',
        'products.brands.index' => 'genel katalog görüntüleme, yetki gerekmiyor',
        'products.categories.index' => 'genel katalog görüntüleme, yetki gerekmiyor',
        'products.warehouses.index' => 'genel katalog görüntüleme, yetki gerekmiyor',
        'products.favorite.toggle' => 'kullanıcı kendi favorisini değiştirir',
        'products.listings.show' => 'genel katalog görüntüleme, yetki gerekmiyor',

        // Controller içinde scope kontrolü yapılıyor (atanan kişi de işlem yapabilir).
        'atelier.assignments.start' => 'yetki controller içinde kontrol ediliyor (bkz. AssignmentController::start)',
        'atelier.assignments.deliver' => 'yetki controller içinde kontrol ediliyor (bkz. AssignmentController::deliver)',
    ];

    private const GATE_PREFIXES = ['can:', 'permission:', 'role:', 'role_or_permission:'];

    public function test_every_authenticated_route_has_an_explicit_authorization_gate(): void
    {
        $offenders = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $isAuthenticated = false;
            $hasGate = false;

            foreach ($middleware as $m) {
                if ($m === 'auth' || str_starts_with($m, 'auth:')) {
                    $isAuthenticated = true;
                }

                foreach (self::GATE_PREFIXES as $prefix) {
                    if (str_starts_with($m, $prefix)) {
                        $hasGate = true;
                        break;
                    }
                }
            }

            if (! $isAuthenticated || $hasGate) {
                continue;
            }

            $key = $route->getName() ?? ($route->methods()[0].' '.$route->uri());

            if (array_key_exists($key, self::ALLOWLIST)) {
                continue;
            }

            $offenders[] = $key;
        }

        $this->assertEmpty(
            $offenders,
            "Şu route'lar auth gerektiriyor ama can:/permission:/role: yetki kapısı yok ve ALLOWLIST'te de değil:\n"
            .implode("\n", $offenders)
            ."\n\nBilinçli bir genel-erişim route'u ise gerekçesiyle bu testin ALLOWLIST'ine ekleyin."
            ." Yetki eksikse route'a ->middleware('can:<izin>') ekleyin (bkz. laravel-authorization skill)."
        );
    }
}
