<?php

namespace Tests\Feature\Superadmin;

use Modules\Superadmin\Services\RouteCatalog;
use Tests\TestCase;

/**
 * Menü Ayarları → Rota kataloğu Türkçe sayfa adı gösterir (RouteCatalog::LABELS).
 * Menülenebilir yeni bir route eklenip karşılığı yazılmazsa katalogda İngilizce
 * otomatik ad çıkar — bu test onu yakalar.
 */
class RouteCatalogLabelsTest extends TestCase
{
    public function test_every_menuable_route_has_turkish_label(): void
    {
        $missing = RouteCatalog::unlabeledRouteNames();

        $this->assertSame(
            [],
            $missing,
            'RouteCatalog::LABELS içinde Türkçe karşılığı olmayan route(lar): ' . implode(', ', $missing),
        );
    }
}
