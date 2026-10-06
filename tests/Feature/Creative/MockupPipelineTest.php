<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMockupGenerator;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupProductTypeResolver;
use Modules\Creative\Services\Mockup\MockupTryOnService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductAttributeOption;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mokap giydirme hattı (Faz 1): bölge/boy çözümleme, kombin → baz görseller → giydirme →
 * onay kuyruğu. AI (Gemini) ve görüntü (Python) adımları sahte sürücülerle; gerçek çağrı yapılmaz.
 */
class MockupPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        Storage::fake('local');
        config(['creative.ai.gemini.api_key' => '', 'creative.mockup.work_disk' => 'local']);
        foreach (['creative.view', 'creative.approve'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->app->bind(MockupGeneratorContract::class, MockMockupGenerator::class);
        FakeMockupVision::$shoeSkin = [];
        $this->app->instance(MockupVisionContract::class, new FakeMockupVision);
        $this->app->instance(GarmentPrepContract::class, new class implements GarmentPrepContract {
            public function prepare(string $sourcePath): string { return $sourcePath; }
        });
    }

    private function png(string $rel): void
    {
        Storage::disk(Media::disk())->put($rel, UploadedFile::fake()->image('x.png', 60, 80)->getContent());
    }

    private function product(string $category = 'Etek', ?string $boy = 'Kısa / Mini'): Product
    {
        $cat = Category::create(['name' => $category, 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        // Ayarlar › Mokap eşleştirmesi (canlıda migration eski config'ten taşıdı).
        $map = ['Etek' => ['bottom', null], 'Kot Pantolon' => ['bottom', 'maxi'], 'Takım' => ['set', null], 'Mont' => ['outerwear', 'knee']][$category] ?? null;
        if ($map) {
            \Modules\Creative\Models\CategoryRegion::create(['category_id' => $cat->id, 'region' => $map[0], 'length' => $map[1]]);
        }
        $attrs = [];
        if ($boy !== null) {
            $attr = ProductAttribute::firstOrCreate(['code' => 'boy'], ['name' => 'Boy', 'type' => 'select']);
            $attrs['boy'] = ProductAttributeOption::firstOrCreate(['product_attribute_id' => $attr->id, 'name' => $boy])->id;
        }
        $product = Product::factory()->create(['category_id' => $cat->id, 'attributes' => $attrs]);
        $this->png("products/{$product->id}/photo.png");
        $product->images()->create(['path' => "products/{$product->id}/photo.png", 'sort_order' => 0, 'is_cover' => true]);

        return $product;
    }

    private function mannequinAndPoses(): Mannequin
    {
        $this->png('mannequins/1/reference.png');
        foreach (['standing_front', 'walking_stride', 'standing_three_qtr', 'seated_floor'] as $i => $key) {
            $this->png("mannequins/poses/{$key}.png");
            Pose::create(['pose_key' => $key, 'label' => $key, 'prompt' => "pose {$key}", 'status' => Pose::STATUS_READY,
                'mockup_regions' => $key === 'seated_floor' ? null : ['bottom', 'top', 'one_piece', 'set', 'outerwear'],
                'sort_order' => $i, 'preview_image_path' => "mannequins/poses/{$key}.png"]);
        }

        return Mannequin::create(['name' => 'M', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED,
            'reference_image_path' => 'mannequins/1/reference.png']);
    }

    public function test_each_size_group_gets_its_own_mannequin_set_with_the_same_look(): void
    {
        Queue::fake();
        $this->mannequinAndPoses();                     // yaşsız manken (havuzda en sona düşer)
        $mk = fn (string $name, string $age) => Mannequin::create(['name' => $name, 'gender' => 'female', 'age_range' => $age,
            'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED, 'reference_image_path' => 'mannequins/1/reference.png']);
        $small = $mk('Kız6', '6-9');
        $big = $mk('Kız10', '10-13');
        $product = $this->product();
        foreach (['7 Yaş', '8 Yaş', '11 Yaş', '14 Yaş'] as $i => $size) {
            $product->variants()->create(['size' => $size, 'sku' => "V{$product->id}-{$i}", 'price' => 1, 'stock' => 0, 'sort_order' => $i]);
        }
        $pipeline = app(MockupPipelineService::class);

        $outfits = collect($pipeline->startFor($product->fresh()));
        $this->assertEqualsCanonicalizing([$small->id, $big->id], $outfits->pluck('mannequin_id')->all());
        $bigOutfit = $outfits->firstWhere('mannequin_id', $big->id);
        $this->assertSame(['11 Yaş', '14 Yaş'], $bigOutfit->meta['sizes']);
        $this->assertNotNull($bigOutfit->meta['mannequin_note']);   // 14 yaş: tam uyan yok
        $this->assertSame([], $pipeline->missingGroups($product->fresh()));

        // İlk grup stilistten geçer; ikincisi aynı kombini + pozları kopyalar.
        $a = $pipeline->run($outfits[0]);
        $b = $pipeline->run($outfits[1]);
        $this->assertSame($a->outfit_sentence, $b->outfit_sentence);
        $this->assertSame($a->poses, $b->poses);
        $this->assertSame($a->id, $b->meta['look_from_outfit']);

        // Sonuç satırları manken başına ayrı (aynı poz iki kez).
        $results = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->get();
        $this->assertCount(6, $results);
        $this->assertSame(3, $results->where('mannequin_id', $small->id)->count());

        // Hepsi hazır → yeniden başlatma okunur hata.
        $this->expectException(\RuntimeException::class);
        $pipeline->startFor($product->fresh());
    }

    /** Tam akışı çalıştırıp ana kare sonucunu verilen etiketlerle reddeder. */
    private function rejectedMockup(array $tags, string $note = ''): array
    {
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->run($pipeline->start($this->product(), $this->mannequinAndPoses()));
        $r = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->whereHas('mockupBase', fn ($q) => $q->whereNull('derived_from_base_id'))->sole();
        (new GenerateOnModelJob($r->id, $r->generation_token))->handle(app(\Modules\Creative\Services\ProductOnModelService::class),
            app(\Modules\Creative\Services\ProductCloseupService::class), app(MockupTryOnService::class));
        $r->refresh()->update(['review_status' => TryonResult::REVIEW_REJECTED, 'review_tags' => $tags, 'review_note' => $note]);

        return [$outfit->fresh(), $r->fresh()];
    }

    public function test_rejection_stage_decides_what_is_regenerated(): void
    {
        Queue::fake();
        $rerun = app(\Modules\Creative\Services\Mockup\MockupRerunService::class);

        // Ürün nedeni → yalnız giydirme, ret ipuçları düzeltme talimatı olur.
        [$outfit, $r] = $this->rejectedMockup(['Renk uyumu'], 'daha koyu yıkama');
        $this->assertSame('tryon', $rerun->plan($r)['stage']);
        $rerun->rerun($r);
        $r->refresh();
        $this->assertSame(TryonResult::STATUS_QUEUED, $r->status);
        $this->assertNull($r->review_status);
        $this->assertStringContainsString('garment color does not match', $r->meta['extra_instructions']);
        $this->assertStringContainsString('daha koyu yıkama', $r->meta['extra_instructions']);
        Queue::assertPushed(GenerateOnModelJob::class, fn ($j) => $j->resultId === $r->id && $j->generationToken === $r->generation_token);
        $this->assertSame(3, MockupBase::where('outfit_id', $outfit->id)->count());     // bazlara dokunulmadı
    }

    public function test_base_stage_regenerates_the_group_bases_with_the_correction(): void
    {
        Queue::fake();
        $rerun = app(\Modules\Creative\Services\Mockup\MockupRerunService::class);
        [$outfit, $r] = $this->rejectedMockup(['Saç', 'Renk uyumu']);       // baz > giydirme

        $this->assertSame('base', $rerun->rerun($r)['stage']);
        $outfit->refresh();
        $this->assertSame(MockupOutfit::STATUS_QUEUED, $outfit->status);
        $this->assertSame(0, MockupBase::where('outfit_id', $outfit->id)->count());
        $this->assertStringContainsString('hair style', $outfit->meta['base_correction']);
        Queue::assertPushed(GenerateMockupJob::class, fn ($j) => $j->outfitId === $outfit->id);

        // Yeniden çalışınca kombin korunur, bazlar ve giydirme satırları yenilenir.
        $sentence = $outfit->outfit_sentence;
        $outfit = app(MockupPipelineService::class)->run($outfit);
        $this->assertSame($sentence, $outfit->outfit_sentence);
        $this->assertSame(3, MockupBase::where('outfit_id', $outfit->id)->count());
        $this->assertSame(TryonResult::STATUS_QUEUED, $r->fresh()->status);
    }

    public function test_stylist_stage_reselects_the_look_and_avoids_the_rejected_combo(): void
    {
        Queue::fake();
        $rerun = app(\Modules\Creative\Services\Mockup\MockupRerunService::class);
        [$outfit, $r] = $this->rejectedMockup(['Kombin ürüne uymuyor', 'Saç']);   // stilist > baz
        $old = [$outfit->pieces['top'], $outfit->pieces['footwear']];

        $this->assertSame('stylist', $rerun->rerun($r)['stage']);
        $outfit->refresh();
        $this->assertNull($outfit->outfit_sentence);
        $this->assertSame([$old], $outfit->meta['avoid_combos']);

        $outfit = app(MockupPipelineService::class)->run($outfit);
        $this->assertNotSame($old, [$outfit->pieces['top'], $outfit->pieces['footwear']]);
    }

    public function test_review_summary_reports_stages_and_qa_vs_human(): void
    {
        Queue::fake();
        [$outfit, $r] = $this->rejectedMockup(['Saç', 'Renk uyumu']);
        $r->update(['qa' => ['status' => 'pass'], 'reviewed_at' => now()]);                    // kaçan hata
        $other = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->whereKeyNot($r->id)->first();
        $other->update(['review_status' => TryonResult::REVIEW_APPROVED, 'reviewed_at' => now(), 'qa' => ['status' => 'fail']]);   // yanlış alarm

        // Yeniden üretim reti satırdan siler ama geçmişte kalır → raporda sayılır.
        app(\Modules\Creative\Services\Mockup\MockupRerunService::class)->rerun($r->fresh());
        $this->assertNull($r->fresh()->review_status);

        $m = app(\Modules\Creative\Services\Mockup\MockupReviewSummary::class)->summarize(now()->subDay());
        $this->assertSame(2, $m['total_reviewed']);
        $this->assertSame(1, $m['total_rejected']);
        $this->assertSame(['Manken / baz görsel' => 1, 'Ürün giydirme' => 1], $m['stage_counts']);
        $this->assertSame(1, $m['qa_vs_human']['missed']);
        $this->assertSame(1, $m['qa_vs_human']['false_alarm']);
        $this->assertSame(1, array_values($m['by_combo'])[0]['rejected']);
    }

    public function test_mockup_closeups_are_cropped_without_ai_except_detail_shots(): void
    {
        Queue::fake();
        [, $r] = $this->rejectedMockup([]);
        $r->update(['review_status' => TryonResult::REVIEW_APPROVED, 'status' => TryonResult::STATUS_DONE]);
        $closeups = app(\Modules\Creative\Services\ProductCloseupService::class)->queue($r->fresh(), ['product', 'fabric']);
        $this->assertSame($r->mannequin_id, $closeups[0]->mannequin_id);

        foreach ($closeups as $c) {
            (new GenerateOnModelJob($c->id, $c->generation_token))->handle(app(\Modules\Creative\Services\ProductOnModelService::class),
                app(\Modules\Creative\Services\ProductCloseupService::class), app(MockupTryOnService::class));
        }

        $byShot = TryonResult::whereIn('id', $closeups->pluck('id'))->get()->keyBy('shot');
        $this->assertSame(['crop', 'crop+fsrcnn'], [$byShot['product']->tryon_driver, $byShot['product']->tryon_model]);
        $this->assertSame(TryonResult::REVIEW_PENDING, $byShot['product']->review_status);
        $this->assertNotSame('crop', $byShot['fabric']->tryon_driver);          // detay kadrajı AI ile
        Storage::disk(Media::disk())->assertExists($byShot['product']->staged_image_path);
    }

    public function test_color_lock_is_applied_and_a_large_remaining_difference_warns(): void
    {
        Queue::fake();
        config(['creative.mockup.color_lock' => true]);
        $this->app->instance(\Modules\Creative\Services\Vision\PythonColorAuditor::class, new class extends \Modules\Creative\Services\Vision\PythonColorAuditor {
            private int $n = 0;
            public function measure(string $o, string $p, string $out): ?array { return ['delta_e' => [14.0, 11.5][$this->n++ % 2], 'confidence' => 'high']; }
        });
        $this->app->instance(\Modules\Creative\Services\Enhancement\PythonColorLock::class, new class extends \Modules\Creative\Services\Enhancement\PythonColorLock {
            public function apply(string $out, string $o, string $p): string { $t = $out . '.locked.png'; copy($out, $t); return $t; }
        });

        [, $r] = $this->rejectedMockup([]);   // akışı çalıştırır (ret etiketi yok)

        $this->assertEquals(['before' => 14.0, 'after' => 11.5, 'locked' => true], $r->qa['color']);   // JSON: 14.0 → 14
        $this->assertSame('warn', $r->qa['status']);
        $this->assertStringContainsString('Renk farkı', collect($r->qa['fidelity']['issues'])->pluck('detail_tr')->implode(' '));
    }

    public function test_only_rejected_mockup_results_can_be_rerun(): void
    {
        Queue::fake();
        [, $r] = $this->rejectedMockup(['Renk uyumu']);
        $r->update(['review_status' => TryonResult::REVIEW_PENDING]);

        $this->expectException(\RuntimeException::class);
        app(\Modules\Creative\Services\Mockup\MockupRerunService::class)->rerun($r->fresh());
    }

    public function test_outerwear_product_gets_an_inner_outfit_and_goes_through_the_pipeline(): void
    {
        Queue::fake();
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->run($pipeline->start($this->product('Mont', null), $this->mannequinAndPoses()));

        $this->assertSame('outerwear', $outfit->region);
        $this->assertSame(MockupOutfit::STATUS_READY, $outfit->status, (string) $outfit->error);
        $this->assertArrayHasKey('top', $outfit->pieces);
        $this->assertArrayHasKey('bottom', $outfit->pieces);
        $this->assertCount(3, TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->get());
    }

    public function test_stylist_picks_from_option_list_and_varies_combos_within_a_category(): void
    {
        Queue::fake();
        $mannequin = $this->mannequinAndPoses();
        $pipeline = app(MockupPipelineService::class);
        $first = $this->product();
        $cat = $first->category_id;

        $a = $pipeline->run($pipeline->start($first, $mannequin));
        $this->assertSame(\Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder::STYLIST_VERSION, (int) $a->stylist_prompt_version);
        // Parçalar seçenek anahtarı; cümle seçeneklerin prompt_en metinlerinden (AI metni değil).
        $this->assertSame('fitted_rib_tee', $a->pieces['top']);
        $this->assertStringStartsWith('A fitted short-sleeve ribbed cotton t-shirt in ', $a->outfit_sentence);
        $this->assertStringContainsString('fully tucked in', $a->outfit_sentence);
        $this->assertStringNotContainsString('mock', $a->outfit_sentence);
        $this->assertSame(0.9, $a->meta['stylist_confidence']);

        // Aynı kategoride ikinci ürün: aynı stilist önerisi gelse de (sahte sürücü hep aynı) ikili tekrarlanmaz.
        $second = $this->product();
        $second->update(['category_id' => $cat]);
        $b = $pipeline->run($pipeline->start($second->fresh(), $mannequin));
        $this->assertNotSame([$a->pieces['top'], $a->pieces['footwear']], [$b->pieces['top'], $b->pieces['footwear']]);
        $this->assertStringContainsString('Çeşitlilik', implode(' ', $b->meta['stylist_warnings']));
    }

    public function test_resolver_reads_region_from_category_and_length_from_boy_attribute(): void
    {
        $r = app(MockupProductTypeResolver::class);

        $this->assertSame(['region' => 'bottom', 'length' => 'mini', 'length_source' => 'attribute'], $r->resolve($this->product('Etek', 'Kısa / Mini')));
        $this->assertSame(['region' => 'bottom', 'length' => 'maxi', 'length_source' => 'category'], $r->resolve($this->product('Kot Pantolon', null)));
        $this->assertSame(['region' => 'set', 'length' => null, 'length_source' => 'ai'], $r->resolve($this->product('Takım', 'Belirtilmemiş')));
        $this->assertSame('override', $r->resolve($this->product('Etek'), null, 'midi')['length_source']);
    }

    public function test_outerwear_category_is_outside_mockup(): void
    {
        $this->expectException(\RuntimeException::class);
        app(MockupProductTypeResolver::class)->resolve($this->product('Ceket', null));
    }

    public function test_start_creates_outfit_and_queues_job(): void
    {
        Queue::fake();
        $outfit = app(MockupPipelineService::class)->start($this->product(), $this->mannequinAndPoses());

        $this->assertSame('bottom', $outfit->region);
        $this->assertSame('mini', $outfit->length);
        Queue::assertPushed(GenerateMockupJob::class, fn ($j) => $j->outfitId === $outfit->id);
    }

    public function test_full_pipeline_lands_in_review_queue_without_touching_legacy_results(): void
    {
        Queue::fake();
        $product = $this->product();
        $mannequin = $this->mannequinAndPoses();
        $front = Pose::where('pose_key', 'standing_front')->first();

        // Aynı ürün+pozda eski hat (referans) sonucu — mokap bunu ezmemeli.
        $legacy = TryonResult::create(['product_id' => $product->id, 'mannequin_id' => $mannequin->id, 'pose_id' => $front->id,
            'status' => TryonResult::STATUS_DONE, 'review_status' => TryonResult::REVIEW_APPROVED, 'staged_image_path' => 'legacy.png']);

        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->run($pipeline->start($product, $mannequin));

        $this->assertSame(MockupOutfit::STATUS_READY, $outfit->status);
        $this->assertNotEmpty($outfit->outfit_sentence);
        // Ana kare + 2 ek poz; mokap dışı poz (seated_floor) stilistin listesine hiç girmez.
        $this->assertEqualsCanonicalizing(['walking_stride', 'standing_three_qtr'], $outfit->poses);
        $bases = MockupBase::where('outfit_id', $outfit->id)->get();
        $this->assertCount(3, $bases);
        $this->assertTrue($bases->every(fn ($b) => $b->status === MockupBase::STATUS_READY && $b->masks['crop'] !== null));
        $this->assertNull($bases->firstWhere('pose_id', $front->id)->derived_from_base_id);
        $this->assertCount(2, $bases->whereNotNull('derived_from_base_id'));
        Storage::disk('local')->assertExists($bases->first()->image_path);

        $results = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->get();
        $this->assertCount(3, $results);
        Queue::assertPushed(GenerateOnModelJob::class, 3);

        // Giydirme işi mokap satırını MockupTryOnService'e yönlendirir → onay kuyruğu.
        $r = $results->firstWhere('pose_id', $front->id);
        (new GenerateOnModelJob($r->id, $r->generation_token))->handle(
            app(\Modules\Creative\Services\ProductOnModelService::class),
            app(\Modules\Creative\Services\ProductCloseupService::class),
            app(MockupTryOnService::class),
        );
        $r->refresh();
        $this->assertSame(TryonResult::STATUS_DONE, $r->status, (string) $r->error);
        $this->assertSame(TryonResult::REVIEW_PENDING, $r->review_status);
        $this->assertEquals(1.0, $r->qa['composite']['outside_unchanged']);  // JSON 1.0 → 1
        Storage::disk(Media::disk())->assertExists($r->staged_image_path);

        $legacy->refresh();
        $this->assertSame(TryonResult::REVIEW_APPROVED, $legacy->review_status);
        $this->assertSame('legacy.png', $legacy->staged_image_path);
    }

    public function test_approving_mockup_result_publishes_to_product_gallery(): void
    {
        Queue::fake();
        $pipeline = app(MockupPipelineService::class);
        $product = $this->product();
        $pipeline->run($pipeline->start($product, $this->mannequinAndPoses()));
        $r = TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->first();
        app(MockupTryOnService::class)->generate($r, $r->generation_token);

        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.approve');
        $this->actingAs($user)->post("/creative/tryon/{$r->id}/approve")->assertRedirect();

        $this->assertNotNull($r->fresh()->product_image_id);
        $this->assertSame(2, $product->images()->count());
    }

    public function test_garment_path_is_normalized_and_validated_before_creating_outfit(): void
    {
        Queue::fake();
        $pipeline = app(MockupPipelineService::class);
        $mannequin = $this->mannequinAndPoses();
        $product = $this->product();
        $abs = base_path('tests/Feature/Creative/__mockup_garment.png');
        file_put_contents($abs, UploadedFile::fake()->image('g.png', 10, 10)->getContent());

        try {
            // Başındaki "/" unutulmuş mutlak yol ve proje kökünden göreli yol → mutlak yola çevrilir.
            $this->assertSame($abs, $pipeline->start($product, $mannequin, garmentPath: ltrim($abs, '/'))->meta['garment_path']);
            $this->assertSame($abs, $pipeline->start($product, $mannequin, garmentPath: 'tests/Feature/Creative/__mockup_garment.png')->meta['garment_path']);
            // Medya diskindeki yol olduğu gibi kalır.
            $this->assertSame("products/{$product->id}/photo.png", $pipeline->start($product, $mannequin, garmentPath: "products/{$product->id}/photo.png")->meta['garment_path']);
        } finally {
            @unlink($abs);
        }

        $before = MockupOutfit::count();
        try {
            $pipeline->start($product, $mannequin, garmentPath: 'yok/boyle-bir-dosya.webp');
            $this->fail('istisna bekleniyordu');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Giysi görseli bulunamadı', $e->getMessage());
        }
        $this->assertSame($before, MockupOutfit::count());
    }

    public function test_outfit_is_processed_once_even_if_run_twice_and_sync_start_does_not_dispatch(): void
    {
        Queue::fake();
        $counter = new class extends MockMockupGenerator {
            public static int $stylistCalls = 0;
            public function __construct() { parent::__construct(app(\Modules\Creative\Services\Ai\Drivers\Mock\MockImageFactory::class)); }
            public function stylist(string $productImagePath, array $context): array { static::$stylistCalls++; return parent::stylist($productImagePath, $context); }
        };
        $counter::$stylistCalls = 0;
        $this->app->instance(MockupGeneratorContract::class, $counter);
        $pipeline = app(MockupPipelineService::class);

        $outfit = $pipeline->start($this->product(), $this->mannequinAndPoses(), dispatch: false);
        Queue::assertNotPushed(GenerateMockupJob::class);

        $pipeline->run($outfit);
        $pipeline->run($outfit->fresh());          // ikinci kopya: hiçbir şey yapmamalı
        (new GenerateMockupJob($outfit->id))->handle($pipeline);

        $this->assertSame(1, $counter::$stylistCalls);
        $this->assertSame(1, MockupBase::where('outfit_id', $outfit->id)->max('attempts'));
        $this->assertSame(MockupOutfit::STATUS_READY, $outfit->fresh()->status);
    }

    public function test_failed_master_marks_outfit_failed(): void
    {
        Queue::fake();
        $this->app->instance(MockupVisionContract::class, new class extends FakeMockupVision {
            public function segment(string $imagePath, string $labelsPath): array { throw new \RuntimeException('segment patladı'); }
        });
        $pipeline = app(MockupPipelineService::class);
        $outfit = $pipeline->start($this->product(), $this->mannequinAndPoses());

        try {
            $pipeline->run($outfit);
            $this->fail('istisna bekleniyordu');
        } catch (\RuntimeException) {
        }

        $this->assertSame(MockupOutfit::STATUS_FAILED, $outfit->fresh()->status);
        $this->assertStringContainsString('segment patladı', (string) $outfit->fresh()->error);
        $this->assertSame(0, TryonResult::where('pipeline', TryonResult::PIPELINE_MOCKUP)->count());
    }
}
