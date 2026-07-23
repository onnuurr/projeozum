<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArchitectureDoctorCommandTest extends TestCase
{
    public function test_it_runs_and_reports_the_self_check_rule(): void
    {
        $exitCode = Artisan::call('architecture:doctor');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('engine.self-check', Artisan::output());
    }

    public function test_json_flag_writes_a_valid_report_file(): void
    {
        $path = storage_path('app/architecture-doctor.json');
        File::delete($path);

        Artisan::call('architecture:doctor', ['--json' => true]);

        $this->assertFileExists($path);

        $decoded = json_decode(File::get($path), associative: true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('category_summaries', $decoded);
        $this->assertArrayHasKey('Engine', $decoded['category_summaries']);
    }

    public function test_ci_flag_exits_zero_when_nothing_critical_fails(): void
    {
        $exitCode = Artisan::call('architecture:doctor', ['--ci' => true]);

        $this->assertSame(0, $exitCode);
    }

    public function test_it_runs_the_real_deptrac_module_boundary_rule(): void
    {
        $exitCode = Artisan::call('architecture:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        // Mevcut Product->Tenant/Atelier borcu deptrac.baseline.yaml'da donduruldu,
        // bu yüzden bugünkü kod tabanı bu kuralı PASSED olarak geçmeli.
        $this->assertStringContainsString('[PASSED] module-boundary.deptrac', $output);
    }

    public function test_it_runs_the_faz_2_migration_discipline_rules(): void
    {
        $exitCode = Artisan::call('architecture:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        // Faz 2 kuralları: mevcut migration'ların hiçbiri ihlal etmediği için (agent
        // doğruladı) hepsi bugün PASSED olarak görünmeli; --ci exit 0 kalır (Experimental).
        $this->assertStringContainsString('[PASSED] migration.down-is-real', $output);
        $this->assertStringContainsString('[PASSED] migration.staged-table-drop', $output);
        $this->assertStringContainsString('[PASSED] migration.soft-delete-should-be-prunable', $output);
        $this->assertStringContainsString('migration.not-edited-after-merge', $output);
    }

    public function test_it_runs_the_faz_3_ai_layer_rules(): void
    {
        $exitCode = Artisan::call('architecture:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        // ai-driver.bypass bugün 0 ihlalle PASSED (agent doğruladı). ai-mock-driver.present
        // ise Product'ın ProductDescriptionGenerator bind()'inin Mock'suz/config-switch'siz
        // doğrudan Gemini'ye bağlı olması yüzünden bilinçli olarak FAILED — Faz 3 planının
        // ilk günden görünür bırakmak istediği tek gerçek bulgu.
        $this->assertStringContainsString('[PASSED] ai-driver.bypass', $output);
        $this->assertStringContainsString('[FAILED] ai-mock-driver.present', $output);
        $this->assertStringContainsString('ProductDescriptionGenerator', $output);
    }

    public function test_it_runs_the_faz_4_controller_discipline_rules(): void
    {
        $exitCode = Artisan::call('architecture:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        // controller.db-table-bypass bugün 0 ihlalle PASSED. controller.large-controller
        // kalıcı olarak rapor-only bir metrik uyarısıdır (asla Stable'a yükselmez, asla
        // blocking olmaz) — plan yazıldığında 5 controller 250 satır eşiğinin üzerindeydi;
        // kod tabanı büyüdükçe bu sayı artabilir, bu yüzden burada tam sayı yerine plandaki
        // orijinal 5 controller'ın hâlâ görünür olduğu doğrulanıyor.
        $this->assertStringContainsString('[PASSED] controller.db-table-bypass', $output);
        $this->assertStringContainsString('[FAILED] controller.large-controller', $output);
        foreach (['SettingsController', 'TryonController', 'ProductController', 'CreativeStudioController', 'PatternController'] as $name) {
            $this->assertStringContainsString($name, $output);
        }
    }

    public function test_it_runs_the_faz_5_tenant_isolation_rule(): void
    {
        $exitCode = Artisan::call('architecture:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        // Product/Order/ProductVariant/Category/Brand üzerinde bugün 0 bare ::all()/
        // zincirsiz ::query()->get() var (agent doğruladı) — bu yüzden PASSED. Kural
        // ileri-dönük bir regresyon muhafızı; gerçek bir bulgu üretmesi manuel olarak
        // (bare Product::all() geçici eklenip geri alınarak) doğrulandı.
        $this->assertStringContainsString('[PASSED] tenant-isolation.scoped-query', $output);
    }

    public function test_it_runs_the_faz_6_read_only_quality_rules(): void
    {
        $exitCode = Artisan::call('architecture:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        // Faz 6 kuralları kalıcı olarak rapor-only — ilk çalıştırmada çok sayıda Finding
        // beklenen davranış, hiçbiri Stable'a yükselmediği için --ci exit 0 kalır.
        $this->assertStringContainsString('[FAILED] quality.phpmd', $output);
        $this->assertStringContainsString('[FAILED] quality.unused-public-service', $output);
    }
}
