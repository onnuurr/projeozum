<?php

namespace Modules\Superadmin\Services;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Superadmin\Models\Menu;

class RouteCatalog
{
    /**
     * Menüye eklenebilir GET route'larını toplar: adlandırılmış, parametresiz,
     * web arayüzü route'ları; zaten menüde olanlar hariç. Modüle göre sıralı.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forMenu(): array
    {
        $inMenu = Menu::query()
            ->whereNotNull('route_name')
            ->pluck('route_name')
            ->all();

        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => self::isMenuable($route, $inMenu))
            ->map(fn (RoutingRoute $route) => [
                'name'   => $route->getName(),
                'uri'    => '/'.ltrim($route->uri(), '/'),
                'module' => self::moduleOf($route),
                'label'  => self::labelOf($route->getName()),
            ])
            ->unique('name')
            ->sortBy([['module', 'asc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $inMenu
     */
    private static function isMenuable(RoutingRoute $route, array $inMenu): bool
    {
        $name = $route->getName();

        if (! $name || in_array($name, $inMenu, true)) {
            return false;
        }

        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }

        // Parametreli (zorunlu/opsiyonel) route'lar gezilebilir bir menü hedefi değil.
        if (Str::contains($route->uri(), '{')) {
            return false;
        }

        $uri = ltrim($route->uri(), '/');

        // API ve dahili (debugbar/ignition/sanctum vb.) route'ları ele.
        if (Str::startsWith($uri, ['api/', '_']) || $uri === 'api') {
            return false;
        }

        if (Str::startsWith($name, ['api.', 'sanctum.', 'ignition.', 'horizon.', 'telescope.'])) {
            return false;
        }

        return true;
    }

    private static function moduleOf(RoutingRoute $route): string
    {
        $action = $route->getActionName();

        if (preg_match('#Modules\\\\([A-Za-z0-9]+)\\\\#', $action, $m) === 1) {
            return $m[1];
        }

        return 'Genel';
    }

    private const ACTION_SUFFIXES = ['index', 'show', 'list', 'create', 'edit', 'store', 'update', 'destroy'];

    private static function labelOf(string $name): string
    {
        $base = $name;

        // Yalnızca bilinen aksiyon ekini at (products.index → products);
        // anlamlı son segmentleri (superadmin.menus) koru.
        if (Str::contains($name, '.')
            && in_array(Str::afterLast($name, '.'), self::ACTION_SUFFIXES, true)) {
            $base = Str::beforeLast($name, '.');
        }

        return Str::of($base)
            ->replace(['.', '-', '_'], ' ')
            ->headline()
            ->toString();
    }
}
