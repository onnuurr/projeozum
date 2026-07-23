<?php

namespace Tests\Unit\ArchitectureDoctor\Quality;

use ArchitectureDoctor\Rules\Quality\PhpmdQualityRule;
use Tests\TestCase;

class PhpmdQualityRuleTest extends TestCase
{
    /**
     * Fixture değil, gerçek phpmd'yi gerçek bir bilinen büyük dosyaya (SettingsController,
     * 620 satır) karşı çalıştıran entegrasyon testi — full-tree tarama yerine hız için
     * sadece bu dosyanın dizinine daraltılmış (aynı sonuç, ~13s yerine ~1s).
     */
    public function test_it_runs_phpmd_and_finds_violations_in_a_known_large_controller(): void
    {
        $rule = new PhpmdQualityRule([base_path('Modules/Superadmin/Http/Controllers')]);

        $findings = $rule->check();

        $this->assertNotEmpty($findings);

        $settingsFinding = collect($findings)->first(
            fn ($f) => str_contains((string) $f->file, 'SettingsController.php')
        );

        $this->assertNotNull($settingsFinding);
    }
}
