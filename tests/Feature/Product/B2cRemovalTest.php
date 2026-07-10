<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Faz 3 / D5: B2C yüzeyi kademeli söküldü. Ana domain /checkout ve /checkout/addresses/*
 * kaldırıldı; B2C controller/model sınıfları silindi. `/cart/*` KORUNUR — portal sepet
 * altyapısı subdomain üzerinden bu route'ları kullanır (regresyon koruması).
 */
class B2cRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_domain_checkout_routes_removed(): void
    {
        $this->get('/checkout')->assertNotFound();
        $this->post('/checkout')->assertNotFound();
        $this->get('/checkout/addresses')->assertNotFound();
    }

    public function test_cart_routes_still_registered(): void
    {
        $this->assertTrue(Route::has('cart.add'), 'cart.add route korunmalı (portal bağımlı).');
        $this->assertTrue(Route::has('cart.update'));
        $this->assertTrue(Route::has('cart.remove'));
        $this->assertTrue(Route::has('cart.clear'));
    }

    public function test_b2c_classes_removed(): void
    {
        $this->assertFalse(class_exists(\Modules\Product\Http\Controllers\CheckoutController::class));
        $this->assertFalse(class_exists(\Modules\Product\Http\Controllers\AddressController::class));
        $this->assertFalse(class_exists(\Modules\Product\Models\UserAddress::class));
    }
}
