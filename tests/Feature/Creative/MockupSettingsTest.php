<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Jobs\AutoStartMockupJob;
use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Models\CategoryRegion;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\StylistOption;
use Modules\Creative\Services\Mockup\MockupSettings;
use Modules\Product\Events\ProductImagesChanged;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mokap Faz 4 backend: Creative ayar sayfası (yetki + kaydetme), Ürün Giydirme'den tekli / toplu
 * başlatma (varsayılan manken, günlük limit), otomatik tetik koşulları. AI çağrısı yapılmaz (kuyruk sahte).
 */
class MockupSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Media::disk());
        Queue::fake();
        foreach (['creative.view', 'creative.asset.manage', 'creative-settings.view', 'mockup-settings.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }

    private function user(array $perms): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo($perms);

        return $u;
    }

    private function mannequin(): Mannequin
    {
        return Mannequin::create(['name' => 'Kız', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED,
            'reference_image_path' => 'mannequins/1/reference.png']);
    }

    private function product(string $region = 'bottom'): Product
    {
        $cat = Category::create(['name' => 'Etek', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        if ($region !== '') {
            CategoryRegion::create(['category_id' => $cat->id, 'region' => $region]);
        }
        $p = Product::factory()->create(['category_id' => $cat->id, 'attributes' => []]);
        Storage::disk(Media::disk())->put("products/{$p->id}/photo.png", UploadedFile::fake()->image('x.png', 60, 80)->getContent());
        $p->images()->create(['path' => "products/{$p->id}/photo.png", 'sort_order' => 0, 'is_cover' => true]);

        return $p;
    }

    public function test_settings_page_requires_page_and_tab_permissions_and_hides_tab_data(): void
    {
        $this->withoutVite();   // sayfa bileşeni build'de (build kullanıcıda)
        $this->actingAs($this->user(['creative.view']))->get('/creative/settings')->assertForbidden();
        // Sayfa yetkisi var ama hiçbir sekme yetkisi yok → sayfa yokmuş gibi 403.
        $this->actingAs($this->user(['creative-settings.view']))->get('/creative/settings')->assertForbidden();

        $this->actingAs($this->user(['creative-settings.view', 'mockup-settings.manage']))->get('/creative/settings')
            ->assertOk()->assertInertia(fn ($p) => $p->component('Creative::Settings', false)->has('mockup.general')->has('mockup.options'));
    }

    public function test_general_settings_categories_options_and_pose_tags_are_saved(): void
    {
        $u = $this->user(['creative-settings.view', 'mockup-settings.manage']);
        $m = $this->mannequin();
        $cat = Category::create(['name' => 'Elbise', 'slug' => 'e-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $pose = Pose::create(['pose_key' => 'walk', 'label' => 'Yürüyüş', 'prompt' => 'walk', 'status' => Pose::STATUS_READY]);

        $this->actingAs($u)->put('/creative/settings/mockup/general', ['auto_trigger' => true, 'daily_limit' => 7, 'mannequins' => [$m->id]])->assertRedirect();
        $s = app(MockupSettings::class);
        $this->assertTrue($s->autoTrigger());
        $this->assertSame(7, $s->dailyLimit());
        $this->assertSame([$m->id], $s->mannequinIds());

        $this->actingAs($u)->put('/creative/settings/mockup/categories', ['rows' => [['category_id' => $cat->id, 'region' => 'one_piece', 'length' => 'midi']]])->assertRedirect();
        $this->assertSame(['region' => 'one_piece', 'length' => 'midi'], $s->regionFor($cat));
        $this->actingAs($u)->put('/creative/settings/mockup/categories', ['rows' => [['category_id' => $cat->id, 'region' => null, 'length' => null]]])->assertRedirect();
        $this->assertNull($s->regionFor($cat));

        $this->actingAs($u)->post('/creative/settings/mockup/options', ['kind' => 'footwear', 'key' => 'rain_boots', 'label_tr' => 'Yağmur botu',
            'prompt_en' => 'rubber rain boots', 'tags' => ['lengths' => ['mini'], 'regions' => []]])->assertRedirect()->assertSessionHasNoErrors();
        $opt = StylistOption::where('key', 'rain_boots')->sole();
        $this->assertSame(['lengths' => ['mini']], $opt->tags);   // boş liste = kısıt yok, saklanmaz
        $this->actingAs($u)->post('/creative/settings/mockup/options', ['kind' => 'footwear', 'key' => 'rain_boots', 'label_tr' => 'x'])
            ->assertSessionHasErrors('key');                     // aynı tür + anahtar tekil
        $this->actingAs($u)->delete('/creative/settings/mockup/options', ['ids' => [$opt->id]])->assertRedirect();
        $this->assertNull(StylistOption::find($opt->id));

        $this->actingAs($u)->put("/creative/settings/mockup/poses/{$pose->id}", ['mockup_regions' => ['bottom'], 'requires' => ['pockets'], 'view' => 'side'])->assertRedirect();
        $pose->refresh();
        $this->assertSame([['bottom'], ['pockets'], 'side'], [$pose->mockup_regions, $pose->requires, $pose->view]);
    }

    public function test_settings_writes_need_the_tab_permission(): void
    {
        $this->actingAs($this->user(['creative-settings.view']))
            ->put('/creative/settings/mockup/general', ['auto_trigger' => true, 'daily_limit' => 1])->assertForbidden();
    }

    public function test_start_picks_a_mannequin_and_respects_daily_limit(): void
    {
        $u = $this->user(['creative.asset.manage']);
        $p = $this->product();

        // Uygun (hazır + onaylı) manken yok → okunur hata, kombin oluşmaz.
        $this->actingAs($u)->post('/creative/tryon/mockup', ['product_id' => $p->id])->assertSessionHas('error');
        $this->assertSame(0, MockupOutfit::count());

        $m = $this->mannequin();
        app(MockupSettings::class)->save(['daily_limit' => 1]);
        $this->actingAs($u)->post('/creative/tryon/mockup', ['product_id' => $p->id])->assertSessionHas('success');
        $this->assertSame($m->id, MockupOutfit::sole()->mannequin_id);
        Queue::assertPushed(GenerateMockupJob::class);

        // Devam eden üretim + günlük limit.
        $this->actingAs($u)->post('/creative/tryon/mockup', ['product_id' => $p->id])->assertSessionHas('error');
        $this->actingAs($u)->post('/creative/tryon/mockup', ['product_id' => $this->product()->id])
            ->assertSessionHas('error', __('creative::messages.mockup.daily_limit', ['limit' => 1]));
    }

    public function test_bulk_start_picks_only_eligible_products_up_to_the_limit(): void
    {
        $m = $this->mannequin();
        app(MockupSettings::class)->save(['daily_limit' => 10]);
        $a = $this->product();
        $b = $this->product();
        $this->product('');                                   // mokap dışı kategori
        $done = $this->product();
        MockupOutfit::forceCreate(['product_id' => $done->id, 'mannequin_id' => $m->id, 'region' => 'bottom', 'length' => 'mini',
            'length_source' => 'attribute', 'status' => MockupOutfit::STATUS_READY]);

        $this->actingAs($this->user(['creative.asset.manage']))->post('/creative/tryon/mockup/bulk', ['limit' => 1])->assertSessionHas('success');
        $this->assertSame([$a->id], MockupOutfit::where('product_id', '!=', $done->id)->pluck('product_id')->all());

        $this->actingAs($this->user(['creative.asset.manage']))->post('/creative/tryon/mockup/bulk', ['limit' => 5])->assertSessionHas('success');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], MockupOutfit::where('product_id', '!=', $done->id)->pluck('product_id')->all());

        $this->actingAs($this->user(['creative.view']))->post('/creative/tryon/mockup/bulk', ['limit' => 5])->assertForbidden();
    }

    public function test_auto_trigger_only_runs_when_enabled_and_product_is_eligible(): void
    {
        $m = $this->mannequin();
        $p = $this->product();
        $s = app(MockupSettings::class);

        event(new ProductImagesChanged($p->id));
        Queue::assertNotPushed(AutoStartMockupJob::class);   // ayar kapalı

        $s->save(['auto_trigger' => true]);
        event(new ProductImagesChanged($p->id));
        Queue::assertPushed(AutoStartMockupJob::class, fn ($j) => $j->productId === $p->id);

        app()->call([new AutoStartMockupJob($p->id), 'handle']);
        $this->assertSame(1, MockupOutfit::where('product_id', $p->id)->count());
        app()->call([new AutoStartMockupJob($p->id), 'handle']);  // zaten kombini var → tekrar başlamaz
        $this->assertSame(1, MockupOutfit::where('product_id', $p->id)->count());

        $other = $this->product('');                           // mokap dışı
        app()->call([new AutoStartMockupJob($other->id), 'handle']);
        $this->assertSame(0, MockupOutfit::where('product_id', $other->id)->count());
    }

    public function test_mannequin_is_picked_by_gender_and_age_with_rotation(): void
    {
        $mk = fn (string $name, string $gender, string $age) => Mannequin::create(['name' => $name, 'gender' => $gender, 'age_range' => $age,
            'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED, 'reference_image_path' => "m/{$name}.png"]);
        $boy = $mk('Erkek5', 'male', '3-5');
        $girl6 = $mk('Kız6', 'female', '6-9');
        $girl10a = $mk('Kız10', 'female', '10-13');
        $girl10b = $mk('Kız13', 'female', '10-13');
        Mannequin::create(['name' => 'Onaysız', 'gender' => 'female', 'age_range' => '14-17', 'status' => Mannequin::STATUS_READY,
            'review_status' => Mannequin::REVIEW_PENDING, 'reference_image_path' => 'm/x.png']);

        $attr = \Modules\Product\Models\ProductAttribute::firstOrCreate(['code' => Product::GENDER_ATTRIBUTE], ['name' => 'Cinsiyet', 'type' => 'select']);
        $optKiz = \Modules\Product\Models\ProductAttributeOption::firstOrCreate(['product_attribute_id' => $attr->id, 'name' => 'Kız'])->id;
        $optErkek = \Modules\Product\Models\ProductAttributeOption::firstOrCreate(['product_attribute_id' => $attr->id, 'name' => 'Erkek'])->id;
        $product = function (int $gender, array $sizes) {
            $p = $this->product();
            $p->update(['attributes' => [Product::GENDER_ATTRIBUTE => $gender]]);
            foreach ($sizes as $i => $size) {
                $p->variants()->create(['size' => $size, 'sku' => "S{$p->id}-{$i}", 'price' => 1, 'stock' => 0, 'sort_order' => $i]);
            }

            return $p->fresh();
        };
        $s = app(MockupSettings::class);

        $this->assertSame([7, 8], $s->productAges($product($optKiz, ['7 Yaş', '8 Yaş'])));
        $this->assertSame($girl6->id, $s->pickMannequin($product($optKiz, ['7 Yaş', '8 Yaş']))['mannequin']->id);
        $this->assertSame($boy->id, $s->pickMannequin($product($optErkek, ['9 Yaş']))['mannequin']->id);   // tek erkek; uzak yaş
        $this->assertNotNull($s->pickMannequin($product($optErkek, ['9 Yaş']))['note']);

        // 14–15 yaş kız: onaysız 14–17 kullanılmaz → en yakın 10–13 ikilisi, notla; ikisi sırayla.
        $teen = $product($optKiz, ['14 Yaş', '15 Yaş']);
        $first = $s->pickMannequin($teen);
        $this->assertSame($girl10a->id, $first['mannequin']->id);
        $this->assertStringContainsString('14–15', $first['note']);
        MockupOutfit::forceCreate(['product_id' => $teen->id, 'mannequin_id' => $girl10a->id, 'region' => 'bottom', 'length' => 'mini', 'length_source' => 'attribute']);
        $this->assertSame($girl10b->id, $s->pickMannequin($teen)['mannequin']->id);

        // Eski biçim {girl, boy} havuz kısıtı sayılmaz (onaylı hepsi).
        \Modules\Creative\Models\CreativeSetting::write(MockupSettings::MANNEQUINS, ['girl' => $girl10b->id, 'boy' => $boy->id]);
        $this->assertSame([], $s->mannequinIds());
        $this->assertSame($girl6->id, $s->pickMannequin($product($optKiz, ['6 Yaş']))['mannequin']->id);

        // Havuz kısıtı: yalnız seçili mankenler.
        $s->save(['mannequins' => [$girl6->id]]);
        $this->assertSame($girl6->id, $s->pickMannequin($teen)['mannequin']->id);
        $this->assertNull($s->pickMannequin($product($optErkek, ['5 Yaş'])));
    }

    public function test_products_endpoint_reports_mockup_region(): void
    {
        $p = $this->product('top');
        $row = collect($this->actingAs($this->user(['creative.view']))->getJson('/creative/tryon/products')
            ->assertOk()->json('data'))->firstWhere('id', $p->id);

        $this->assertSame('top', $row['mockup_region']);
    }
}
