<?php

namespace Tests\Feature\Creative;

use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Drivers\Mock\MockImageFactory;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMockupGenerator;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupTryOnService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Senaryolu sahte AI: kontrol cevapları sırayla verilir, çağrılar sayılır. */
class QaScriptedGenerator extends MockMockupGenerator
{
    public static array $consistency = [];
    public static array $fidelity = [];
    public static array $calls = ['master' => 0, 'derived' => 0, 'tryon' => 0, 'fidelity' => 0, 'consistency' => 0];
    public static array $corrections = [];

    public function __construct() { parent::__construct(app(MockImageFactory::class)); }

    public static function reset(): void
    {
        self::$consistency = self::$fidelity = self::$corrections = [];
        self::$calls = ['master' => 0, 'derived' => 0, 'tryon' => 0, 'fidelity' => 0, 'consistency' => 0];
    }

    public function masterBase(string $m, string $p, array $o): string { self::$calls['master']++; return parent::masterBase($m, $p, $o); }
    public function derivedBase(string $m, string $p, array $o): string { self::$calls['derived']++; return parent::derivedBase($m, $p, $o); }

    public function tryOn(string $c, array $i, string $r, string $l, ?string $correction = null): string
    {
        self::$calls['tryon']++;
        self::$corrections[] = $correction;

        return parent::tryOn($c, $i, $r, $l, $correction);
    }

    public function checkBaseConsistency(string $pairPath): array
    {
        self::$calls['consistency']++;

        return array_shift(self::$consistency) ?? ['verdict' => 'pass', 'issues' => []];
    }

    public function checkFidelity(string $pairPath, string $region, string $view): array
    {
        self::$calls['fidelity']++;

        return array_shift(self::$fidelity) ?? ['verdict' => 'pass', 'issues' => []];
    }
}

/**
 * Faz 2 otomatik kontroller: baz tutarlılığı / çıplak ayak → en fazla 1 yeniden üretim; sadakat
 * kontrolünden kalan giydirme → bulgularla en fazla 1 yeniden deneme; sonuç her durumda onaya düşer.
 */
class MockupQaTest extends TestCase
{
    use RefreshDatabase;

    private const HAIR_FAIL = ['verdict' => 'fail', 'issues' => [['aspect' => 'hair', 'detail_tr' => 'Saç açık, ana karede at kuyruğu.']]];
    private const TIER_FAIL = ['verdict' => 'fail', 'issues' => [['severity' => 'major', 'detail_tr' => 'Üründe olmayan kat dikişi eklenmiş.', 'fix_en' => 'Remove the added tier seams.']]];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        Storage::fake('local');
        config(['creative.ai.gemini.api_key' => '', 'creative.mockup.work_disk' => 'local', 'creative.mockup.qa.max_retries' => 1, 'creative.mockup.qa.enabled' => true]);
        foreach (['creative.view', 'creative.approve'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        QaScriptedGenerator::reset();
        FakeMockupVision::$shoeSkin = [];
        $this->app->instance(MockupGeneratorContract::class, new QaScriptedGenerator);
        $this->app->instance(MockupVisionContract::class, new FakeMockupVision);
        $this->app->instance(GarmentPrepContract::class, new class implements GarmentPrepContract {
            public function prepare(string $sourcePath): string { return $sourcePath; }
        });
        Queue::fake();
    }

    private function png(string $rel): void
    {
        Storage::disk(Media::disk())->put($rel, UploadedFile::fake()->image('x.png', 60, 80)->getContent());
    }

    /** Ana kare + 1 türetilen poz ile kombini çalıştırır. */
    private function runOutfit(): \Modules\Creative\Models\MockupOutfit
    {
        config(['creative.mockup.extra_poses' => 1]);
        $this->png('mannequins/1/reference.png');
        foreach (['standing_front', 'walking_stride'] as $i => $key) {
            $this->png("mannequins/poses/{$key}.png");
            Pose::create(['pose_key' => $key, 'label' => $key, 'prompt' => "pose {$key}", 'status' => Pose::STATUS_READY,
                'mockup_regions' => $key === 'seated_floor' ? null : ['bottom', 'top', 'one_piece', 'set'], 'sort_order' => $i, 'preview_image_path' => "mannequins/poses/{$key}.png"]);
        }
        $mannequin = Mannequin::create(['name' => 'M', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED, 'reference_image_path' => 'mannequins/1/reference.png']);
        $cat = Category::create(['name' => 'Etek', 'slug' => 'e-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::factory()->create(['category_id' => $cat->id]);
        $this->png("products/{$product->id}/photo.png");
        $product->images()->create(['path' => "products/{$product->id}/photo.png", 'sort_order' => 0, 'is_cover' => true]);

        $pipeline = app(MockupPipelineService::class);

        return $pipeline->run($pipeline->start($product, $mannequin, length: 'mini', dispatch: false));
    }

    private function generate(string $poseKey): TryonResult
    {
        $r = TryonResult::where('pipeline', 'mockup')->where('pose_id', Pose::where('pose_key', $poseKey)->value('id'))->firstOrFail();

        return app(MockupTryOnService::class)->generate($r, $r->generation_token)->fresh();
    }

    public function test_inconsistent_derived_pose_is_rederived_once_and_passes(): void
    {
        QaScriptedGenerator::$consistency = [self::HAIR_FAIL, ['verdict' => 'pass', 'issues' => []]];
        $this->runOutfit();

        $this->assertSame(2, QaScriptedGenerator::$calls['derived']);
        $derived = MockupBase::whereNotNull('derived_from_base_id')->sole();
        $this->assertSame('pass', $derived->qa['status']);
        $this->assertSame(1, $derived->qa['retries']);
    }

    public function test_persistent_inconsistency_stops_after_max_retries_and_warns_the_reviewer(): void
    {
        QaScriptedGenerator::$consistency = [self::HAIR_FAIL, self::HAIR_FAIL, self::HAIR_FAIL];
        $this->runOutfit();

        $this->assertSame(2, QaScriptedGenerator::$calls['derived']);          // 1 + en fazla 1 yeniden
        $this->assertSame('warn', MockupBase::whereNotNull('derived_from_base_id')->sole()->qa['status']);

        $r = $this->generate('walking_stride');
        $this->assertSame('warn', $r->qa['status']);
        $this->assertSame(TryonResult::REVIEW_PENDING, $r->review_status);   // yine onaya düşer
        $this->assertContains('Saç açık, ana karede at kuyruğu.', collect($r->qa['base_issues'])->pluck('detail_tr')->all());
    }

    public function test_minor_accessory_difference_warns_without_regenerating(): void
    {
        QaScriptedGenerator::$consistency = [['verdict' => 'warn', 'issues' => [['aspect' => 'accessory', 'detail_tr' => 'Saç tokası yalnız bu pozda görünüyor.']]]];
        $this->runOutfit();

        $this->assertSame(1, QaScriptedGenerator::$calls['derived']);
        $this->assertSame('warn', MockupBase::whereNotNull('derived_from_base_id')->sole()->qa['status']);
    }

    public function test_barefoot_master_is_regenerated(): void
    {
        FakeMockupVision::$shoeSkin = [0.95, 0.05];
        $this->runOutfit();

        $this->assertSame(2, QaScriptedGenerator::$calls['master']);
        $this->assertTrue(MockupBase::whereNull('derived_from_base_id')->sole()->qa['shoes']['ok']);
    }

    public function test_failed_fidelity_retries_once_with_the_findings_as_correction(): void
    {
        $this->runOutfit();
        QaScriptedGenerator::$fidelity = [self::TIER_FAIL, ['verdict' => 'pass', 'issues' => []]];

        $r = $this->generate('standing_front');

        $this->assertSame(2, QaScriptedGenerator::$calls['tryon']);
        $this->assertNull(QaScriptedGenerator::$corrections[0]);
        $this->assertStringContainsString('Remove the added tier seams.', (string) QaScriptedGenerator::$corrections[1]);
        $this->assertSame('pass', $r->qa['status']);
        $this->assertSame(2, $r->qa['attempts']);
    }

    public function test_fidelity_failing_twice_lands_in_review_as_fail_without_more_attempts(): void
    {
        $this->runOutfit();
        QaScriptedGenerator::$fidelity = [self::TIER_FAIL, self::TIER_FAIL, self::TIER_FAIL];

        $r = $this->generate('standing_front');

        $this->assertSame(2, QaScriptedGenerator::$calls['tryon']);
        $this->assertSame('fail', $r->qa['status']);
        $this->assertSame(TryonResult::REVIEW_PENDING, $r->review_status);
    }

    public function test_qa_disabled_makes_no_check_calls(): void
    {
        config(['creative.mockup.qa.enabled' => false]);
        QaScriptedGenerator::$consistency = [self::HAIR_FAIL];
        $this->runOutfit();
        $r = $this->generate('standing_front');

        $this->assertSame(0, QaScriptedGenerator::$calls['consistency'] + QaScriptedGenerator::$calls['fidelity']);
        $this->assertSame(1, QaScriptedGenerator::$calls['derived']);
        $this->assertSame(1, QaScriptedGenerator::$calls['tryon']);
        $this->assertSame('pass', $r->qa['status']);
    }

    public function test_results_api_exposes_qa_summary_for_the_review_card(): void
    {
        $this->runOutfit();
        QaScriptedGenerator::$fidelity = [self::TIER_FAIL, self::TIER_FAIL];
        $r = $this->generate('standing_front');
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('creative.view');

        $row = collect($this->actingAs($user)->getJson('/creative/tryon/results')->assertOk()->json('data'))->firstWhere('id', $r->id);

        $this->assertSame('fail', $row['qa']['status']);
        $this->assertSame(2, $row['qa']['attempts']);
        $this->assertContains('Üründe olmayan kat dikişi eklenmiş.', $row['qa']['issues']);
    }
}
