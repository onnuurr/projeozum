<?php

namespace Tests\Unit\ArchitectureDoctor\AiLayer;

use ArchitectureDoctor\Rules\AiLayer\AiMockDriverPresentRule;
use Tests\TestCase;

class AiMockDriverPresentRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/AiLayer/Providers';

    public function test_it_flags_a_bind_without_any_mock_alternative(): void
    {
        $rule = new AiMockDriverPresentRule([self::FIXTURES]);

        $findings = $rule->check();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('BadContract', $findings[0]->message);
        $this->assertStringContainsString('BadServiceProvider.php', (string) $findings[0]->file);
    }

    public function test_it_does_not_flag_a_bind_with_a_mock_alternative(): void
    {
        $rule = new AiMockDriverPresentRule([self::FIXTURES]);

        $findings = $rule->check();
        $messages = array_map(fn ($f) => $f->message, $findings);

        $this->assertFalse(collect($messages)->contains(fn ($m) => str_contains($m, 'GoodContract')));
    }

    /**
     * Faz 3 planındaki gerçek bulgu: Product'ın ProductDescriptionGenerator bind()'i
     * (ProductServiceProvider.php:28) Mock'suz, config-switch'siz doğrudan Gemini'ye
     * bağlı — fixture değil, gerçek dosyaya karşı entegrasyon testi.
     */
    public function test_it_detects_the_real_product_description_generator_violation(): void
    {
        $rule = new AiMockDriverPresentRule();

        $findings = $rule->check();

        $productFinding = collect($findings)->first(
            fn ($f) => str_contains((string) $f->file, 'Modules/Product/Providers/ProductServiceProvider.php')
        );

        $this->assertNotNull($productFinding);
        $this->assertStringContainsString('ProductDescriptionGenerator', $productFinding->message);
    }
}
