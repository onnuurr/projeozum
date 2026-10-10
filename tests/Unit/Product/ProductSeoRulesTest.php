<?php

namespace Tests\Unit\Product;

use Modules\Product\Services\Ai\ProductSeoRules;
use PHPUnit\Framework\TestCase;

class ProductSeoRulesTest extends TestCase
{
    public function test_text_is_cleaned_and_cut_at_word_boundary(): void
    {
        $rules = new ProductSeoRules;

        $this->assertSame('Basic Tişört', $rules->text('  "**Basic   Tişört**" ', 60));
        $this->assertSame('Bir iki üç', $rules->text('Bir iki üç dört', 12));
        $this->assertNull($rules->text('  ** ', 60));
        $this->assertNull($rules->text(['dizi'], 60));
    }

    public function test_keywords_are_lowercased_deduped_and_capped(): void
    {
        $rules = new ProductSeoRules;

        $this->assertSame('ıslak, istanbul, tişört', $rules->keywords(['ISLAK', 'İstanbul', 'Tişört', 'tişört', '']));
        $this->assertSame('a, b', $rules->keywords('a, b,  ,a'));
        $this->assertCount(ProductSeoRules::KEYWORDS_MAX, explode(', ', $rules->keywords(range(1, 30))));
        $this->assertNull($rules->keywords(str_repeat('x', 41)));
    }
}
