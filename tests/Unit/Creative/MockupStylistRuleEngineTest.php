<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Mockup\MockupStylistRuleEngine;
use Tests\TestCase;

/**
 * Mokap stilisti kural motoru (Faz 3): AI'nın anahtar seçimleri doğrulanır, uyumsuzlar elenir,
 * çeşitlilik kuralı uygulanır, kombin cümlesi yalnız seçeneklerin prompt_en metinlerinden kurulur.
 * Hiçbir AI/DB çağrısı yapmaz.
 */
class MockupStylistRuleEngineTest extends TestCase
{
    private const ALL = ['bottom', 'top', 'one_piece', 'set'];

    private function catalog(): array
    {
        $o = fn (string $en, array $tags = []) => ['label_tr' => $en, 'prompt_en' => $en, 'tags' => $tags];

        return [
            'top' => [
                'rib_tee'    => $o('a ribbed t-shirt', ['regions' => ['bottom'], 'colorable' => true]),
                'blouse'     => $o('a puff-sleeve blouse', ['regions' => ['bottom'], 'genders' => ['girl'], 'colorable' => true]),
                'shirt'      => $o('a button-up shirt', ['regions' => ['bottom']]),
            ],
            'bottom' => [
                'jeans'      => $o('straight-leg jeans', ['regions' => ['top']]),
            ],
            'footwear' => [
                'sneakers'   => $o('canvas sneakers', ['regions' => self::ALL]),
                'mary_janes' => $o('Mary Jane shoes', ['regions' => self::ALL, 'genders' => ['girl'], 'lengths' => ['mini', 'knee']]),
                'sandals'    => $o('strap sandals', ['regions' => self::ALL]),
            ],
            'socks' => [
                'none'       => $o(''),
                'ankle'      => $o('white ankle socks', ['lengths' => ['mini', 'knee']]),
            ],
            'accessory' => [
                'none'       => $o(''),
                'headband'   => $o('a headband', ['placement' => 'hair']),
                'watch'      => $o('a wristwatch', ['placement' => 'wrist']),
                'bag'        => $o('a crossbody bag', ['placement' => 'chest']),
            ],
            'color' => [
                'yellow'     => $o('pale yellow'),
                'navy'       => $o('navy'),
            ],
        ];
    }

    private function poses(): array
    {
        return [
            'walking'   => ['regions' => self::ALL, 'requires' => []],
            'pocket'    => ['regions' => self::ALL, 'requires' => ['pockets']],
            'top_only'  => ['regions' => ['top'], 'requires' => []],
            'three_qtr' => ['regions' => self::ALL, 'requires' => []],
        ];
    }

    private function resolve(array $ai, array $ctx = []): array
    {
        return (new MockupStylistRuleEngine())->resolve($ai, $this->catalog(), $this->poses(),
            [...['region' => 'bottom', 'length' => 'mini', 'gender' => 'girl', 'extra_poses' => 2, 'recent' => []], ...$ctx]);
    }

    public function test_valid_picks_build_the_sentence_from_option_texts_only(): void
    {
        $r = $this->resolve(['picks' => ['top' => ['rib_tee'], 'color' => ['yellow'], 'footwear' => ['mary_janes'],
            'socks' => ['ankle'], 'accessory' => ['headband']], 'confidence' => 0.9]);

        $this->assertSame(['top' => 'rib_tee', 'footwear' => 'mary_janes', 'color' => 'yellow', 'socks' => 'ankle', 'accessory' => 'headband'], $r['pieces']);
        $this->assertSame('A ribbed t-shirt in pale yellow, fully tucked in, with Mary Jane shoes, white ankle socks and a headband.', $r['sentence']);
        $this->assertSame([], $r['warnings']);
    }

    public function test_unknown_or_unsuitable_keys_fall_back_to_the_next_valid_option_with_a_warning(): void
    {
        // Erkek ürününe kız bluzu ve Mary Jane önerilmiş + listede olmayan anahtar.
        $r = $this->resolve(['picks' => ['top' => ['blouse', 'made_up'], 'footwear' => ['mary_janes', 'sandals']]], ['gender' => 'boy']);

        $this->assertSame('rib_tee', $r['pieces']['top']);
        $this->assertSame('sandals', $r['pieces']['footwear']);
        $this->assertNotEmpty($r['warnings']);
    }

    public function test_long_products_hide_socks_and_reject_length_limited_shoes(): void
    {
        $r = $this->resolve(['picks' => ['top' => ['shirt'], 'footwear' => ['mary_janes'], 'socks' => ['ankle']]], ['length' => 'maxi']);

        $this->assertSame('none', $r['pieces']['socks']);
        $this->assertNotSame('mary_janes', $r['pieces']['footwear']);
        $this->assertArrayNotHasKey('color', $r['pieces']);        // gömlek renklenebilir değil
        $this->assertStringNotContainsString('socks', $r['sentence']);
    }

    public function test_top_products_get_a_bottom_and_no_chest_accessory(): void
    {
        $r = $this->resolve(['picks' => ['bottom' => ['jeans'], 'footwear' => ['sneakers'], 'accessory' => ['bag']]], ['region' => 'top']);

        $this->assertSame('jeans', $r['pieces']['bottom']);
        $this->assertArrayNotHasKey('top', $r['pieces']);
        $this->assertSame('none', $r['pieces']['accessory']);
        $this->assertStringNotContainsString('tucked', $r['sentence']);
    }

    public function test_sets_only_get_footwear_socks_and_a_hair_accessory(): void
    {
        $r = $this->resolve(['picks' => ['footwear' => ['sneakers'], 'accessory' => ['watch', 'headband']]], ['region' => 'set']);

        $this->assertSame(['footwear', 'socks', 'accessory'], array_keys($r['pieces']));
        $this->assertSame('headband', $r['pieces']['accessory']);
        $this->assertSame('Canvas sneakers and a headband.', $r['sentence']);   // çorap seçilmedi → listedeki ilk: none
    }

    public function test_diversity_rule_skips_combos_used_recently_in_the_category(): void
    {
        $ai = ['picks' => ['top' => ['rib_tee', 'shirt'], 'footwear' => ['sneakers', 'sandals']]];

        $r = $this->resolve($ai, ['recent' => [['rib_tee', 'sneakers']]]);
        $this->assertSame(['rib_tee', 'sandals'], [$r['pieces']['top'], $r['pieces']['footwear']]);
        $this->assertStringContainsString('Çeşitlilik', implode(' ', $r['warnings']));

        // Bütün ikililer kullanılmışsa yine de bir kombin döner (ilk tercih).
        $all = [];
        foreach (['rib_tee', 'blouse', 'shirt'] as $t) {
            foreach (['sneakers', 'mary_janes', 'sandals'] as $f) {
                $all[] = [$t, $f];
            }
        }
        $this->assertSame('rib_tee', $this->resolve($ai, ['recent' => $all])['pieces']['top']);
    }

    public function test_poses_respect_region_and_product_features(): void
    {
        $r = $this->resolve(['poses' => ['pocket', 'top_only', 'three_qtr']]);
        $this->assertSame(['three_qtr', 'walking'], $r['poses']);      // cep yok, bölge uymuyor → listeden tamamlanır

        $r = $this->resolve(['poses' => ['pocket'], 'features' => ['pockets']]);
        $this->assertSame(['pocket', 'walking'], $r['poses']);
    }

    public function test_low_confidence_is_flagged_for_the_reviewer(): void
    {
        $r = $this->resolve(['picks' => ['top' => ['rib_tee']], 'confidence' => 0.3]);

        $this->assertSame(0.3, $r['confidence']);
        $this->assertStringContainsString('emin değil', implode(' ', $r['warnings']));
    }
}
