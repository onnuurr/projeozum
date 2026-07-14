<?php

namespace Tests\Unit\Superadmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\Services\RouteCatalog;
use Tests\TestCase;

class RouteCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regresyon: 'portal.marketplace.index' gibi domain'i {slug} bekleyen route'lar
     * URI'de parametre taşımadığı için eskiden "menüye eklenebilir" listesine giriyordu.
     * Menu::resolveTo() bu route_name için route() çağırınca UrlGenerationException
     * fırlatıyor ve tüm uygulamayı kırıyordu (bkz. MenuTreeBuilderTest). isMenuable()
     * artık domain-parametreli route'ları da eler.
     */
    public function test_excludes_domain_scoped_routes_from_menu_candidates(): void
    {
        $names = collect(RouteCatalog::forMenu())->pluck('name');

        $this->assertFalse($names->contains('portal.marketplace.index'));
        $this->assertFalse($names->contains('portal.dashboard'));
    }
}
