<?php

namespace Tests\Feature\Creative;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Creative\Models\BrandKit;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\BrandTokenService;
use Modules\Creative\Services\CopyService;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * On-image copy zincirini uçtan uca doğrular (renderer hariç):
 * brand_kits copy kolonları → BrandTokenService['copy'] → CopyService slot
 * ayıklama → (test ortamında anahtarsız) Mock sürücü → CopyConstraints ban
 * güvencesi. Anahtar olmadığı için binding otomatik MockCopyGenerator'a düşer.
 */
class CopyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function template(array $slotKeys): CreativeTemplate
    {
        $slots = [];
        foreach ($slotKeys as $key) {
            $slots[] = ['key' => $key, 'type' => 'text', 'x' => 0, 'y' => 0];
        }
        $slots[] = ['key' => 'product_image', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10];

        return CreativeTemplate::create([
            'name'      => 'T',
            'svg_path'  => 'templates/t.svg',
            'width'     => 1080,
            'height'    => 1920,
            'slots'     => $slots,
            'is_active' => true,
        ]);
    }

    public function test_brand_token_service_exposes_copy_tokens(): void
    {
        BrandKit::create([
            'name'         => 'Marka',
            'is_default'   => true,
            'design_brief' => 'Sade, doğal marka dili',
            'tone'         => 'sıcak',
            'cta_phrases'  => ['Hemen keşfet', 'Sepete ekle'],
            'banned_words' => ['ucuz', 'indirim'],
        ]);

        $copy = app(BrandTokenService::class)->tokens()['copy'];

        $this->assertSame('Sade, doğal marka dili', $copy['brief']);
        $this->assertSame('sıcak', $copy['tone']);
        $this->assertSame(['Hemen keşfet', 'Sepete ekle'], $copy['cta_phrases']);
        $this->assertContains('ucuz', $copy['banned_words']);
    }

    public function test_copy_service_fills_copy_slots_and_respects_brand(): void
    {
        BrandKit::create([
            'name'         => 'Marka',
            'is_default'   => true,
            'cta_phrases'  => ['Sepete ekle'],
            'banned_words' => ['bedava'],
        ]);

        $product  = Product::factory()->create(['name' => 'Keten Gömlek']);
        $template = $this->template(['headline', 'cta']);

        $copy = app(CopyService::class)->forTemplate($product, $template);

        // Yalnız copy slotları döner (product_image, product_name yok).
        $this->assertArrayHasKey('headline', $copy);
        $this->assertArrayHasKey('cta', $copy);
        $this->assertArrayNotHasKey('product_image', $copy);
        $this->assertArrayNotHasKey('product_name', $copy);

        // CTA markanın ifadesi; hiçbir alanda yasaklı kelime yok.
        $this->assertSame('Sepete ekle', $copy['cta']);
        $this->assertStringNotContainsStringIgnoringCase('bedava', implode(' ', $copy));
    }

    public function test_copy_service_returns_empty_when_template_has_no_copy_slots(): void
    {
        BrandKit::create(['name' => 'Marka', 'is_default' => true]);

        $product  = Product::factory()->create();
        // Yalnız product_name (copy değil) + görsel slotu var.
        $template = $this->template(['product_name']);

        $this->assertSame([], app(CopyService::class)->forTemplate($product, $template));
    }
}
