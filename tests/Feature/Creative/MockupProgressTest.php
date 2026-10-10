<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Ürün Giydirme › "Arka planda çalışan mokaplar": aşama kombin/baz/sonuç durumundan türetilir. */
class MockupProgressTest extends TestCase
{
    use RefreshDatabase;

    private function outfit(array $attrs = []): MockupOutfit
    {
        $mannequin = Mannequin::create(['name' => 'Kız 3-5', 'status' => Mannequin::STATUS_READY, 'review_status' => Mannequin::REVIEW_APPROVED,
            'reference_image_path' => 'mannequins/1/reference.png']);

        return MockupOutfit::create(['product_id' => Product::factory()->create()->id, 'mannequin_id' => $mannequin->id,
            'region' => 'one_piece', 'length' => '', 'length_source' => 'ai', 'status' => MockupOutfit::STATUS_GENERATING,
            'meta' => ['batch' => true, 'sizes' => ['5 Yaş']], ...$attrs]);
    }

    private function base(MockupOutfit $o, ?int $master, string $status): MockupBase
    {
        $pose = Pose::create(['pose_key' => 'p' . uniqid(), 'label' => 'p', 'prompt' => 'p', 'status' => Pose::STATUS_READY]);

        return MockupBase::create(['outfit_id' => $o->id, 'pose_id' => $pose->id, 'derived_from_base_id' => $master, 'status' => $status]);
    }

    private function progress(): array
    {
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        return $this->actingAs($user)->getJson('/creative/tryon/mockup/progress')->assertOk()->json('items');
    }

    public function test_stage_follows_stylist_master_poses_and_tryon(): void
    {
        $o = $this->outfit();
        $this->assertSame([1, 'Kombin seçiliyor'], [$this->progress()[0]['step'], $this->progress()[0]['label']]);

        $o->update(['pieces' => ['footwear' => 'x']]);
        $master = $this->base($o, null, MockupBase::STATUS_GENERATING);
        $this->assertSame(2, $this->progress()[0]['step']);

        $master->update(['status' => MockupBase::STATUS_READY]);
        $pose = $this->base($o, $master->id, MockupBase::STATUS_GENERATING);
        $this->base($o, $master->id, MockupBase::STATUS_READY);
        $row = $this->progress()[0];
        $this->assertSame([3, 1, 2], [$row['step'], $row['done'], $row['total']]);

        $pose->update(['status' => MockupBase::STATUS_READY]);
        $product = $o->product_id;
        $mk = fn (MockupBase $b, string $status) => TryonResult::create(['product_id' => $product, 'mannequin_id' => $o->mannequin_id, 'pose_id' => $b->pose_id,
            'pipeline' => 'mockup', 'mockup_base_id' => $b->id, 'shot' => 'full', 'status' => $status]);
        $mk($master, TryonResult::STATUS_DONE);
        $last = $mk($pose, TryonResult::STATUS_GENERATING);
        $row = $this->progress()[0];
        $this->assertSame(['running', 4, 1, 2], [$row['state'], $row['step'], $row['done'], $row['total']]);

        $last->update(['status' => TryonResult::STATUS_DONE]);
        $o->update(['status' => MockupOutfit::STATUS_READY]);
        $row = $this->progress()[0];
        $this->assertSame(['done', 100], [$row['state'], $row['percent']]);
    }

    public function test_failed_outfit_shows_its_error_and_requires_view_permission(): void
    {
        $this->outfit(['status' => MockupOutfit::STATUS_FAILED, 'error' => 'Bütçe aşıldı']);
        $row = $this->progress()[0];
        $this->assertSame(['failed', 'Hata: Bütçe aşıldı'], [$row['state'], $row['label']]);

        $this->actingAs(User::factory()->create())->getJson('/creative/tryon/mockup/progress')->assertForbidden();
    }
}
