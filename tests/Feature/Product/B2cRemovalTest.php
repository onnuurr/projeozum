<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Faz 3 / D5: B2C yüzeyi kademeli söküldü. Ana domain /checkout ve /checkout/addresses/*
 * kaldırıldı; B2C controller/model sınıfları silindi. 2026-09-22'de Bayi Portalı ile
 * birlikte sepet de tamamen kaldırıldı (34f2221) — `/cart/*` artık yok.
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

    public function test_cart_routes_removed(): void
    {
        foreach (['cart.add', 'cart.update', 'cart.remove', 'cart.clear'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} route'u kaldırılmış olmalı.");
        }
    }

    public function test_b2c_classes_removed(): void
    {
        $this->assertFalse(class_exists(\Modules\Product\Http\Controllers\CheckoutController::class));
        $this->assertFalse(class_exists(\Modules\Product\Http\Controllers\AddressController::class));
        $this->assertFalse(class_exists(\Modules\Product\Models\UserAddress::class));
    }
}
