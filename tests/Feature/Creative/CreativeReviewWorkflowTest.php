<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Notifications\ImagePendingReviewNotification;
use Modules\Creative\Notifications\ImageReviewDecisionNotification;
use Modules\Creative\Services\ReviewNotifier;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Manken/giydirme görsellerinin insan onay akışını doğrular: üretici hariç
 * yöneticilere bildirim, onay/ret geçişleri, mannequin üreticisinin kendi
 * işini onaylayamaması (tryon'da bu kısıt yok — üretici kendi giydirme
 * sonucunu da onaylayıp reddedebilir), ve Tryon onayının product_images'ı
 * ANCAK onay anında yazması.
 */
class CreativeReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Modül Inertia sayfaları test view-finder'ında çözülmez; varlık kontrolünü kapat.
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.approve', 'guard_name' => 'web']);
    }

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.approve');

        return $user;
    }

    private function creator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        return $user;
    }

    private function pendingMannequin(User $creator): Mannequin
    {
        return Mannequin::create([
            'name'                 => 'Test Manken',
            'status'               => Mannequin::STATUS_READY,
            'reference_image_path' => 'mannequins/1/reference.png',
            'created_by'           => $creator->id,
            'review_status'        => Mannequin::REVIEW_PENDING,
        ]);
    }

    // ── Manken ──────────────────────────────────────────────────────────

    public function test_reviewer_can_approve_mannequin_and_creator_is_notified(): void
    {
        Notification::fake();
        $creator  = $this->creator();
        $reviewer = $this->reviewer();
        $m        = $this->pendingMannequin($creator);

        $this->actingAs($reviewer)
            ->post("/creative/mannequins/{$m->id}/approve")
            ->assertRedirect();

        $m->refresh();
        $this->assertSame(Mannequin::REVIEW_APPROVED, $m->review_status);
        $this->assertSame($reviewer->id, $m->reviewed_by);
        Notification::assertSentTo($creator, ImageReviewDecisionNotification::class);
    }

    public function test_reject_requires_a_substantive_reason(): void
    {
        $creator  = $this->creator();
        $reviewer = $this->reviewer();
        $m        = $this->pendingMannequin($creator);

        $this->actingAs($reviewer)
            ->post("/creative/mannequins/{$m->id}/reject", ['reason' => 'kısa'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Mannequin::REVIEW_PENDING, $m->fresh()->review_status);
    }

    public function test_reject_with_valid_reason_notifies_creator(): void
    {
        Notification::fake();
        $creator  = $this->creator();
        $reviewer = $this->reviewer();
        $m        = $this->pendingMannequin($creator);

        $this->actingAs($reviewer)
            ->post("/creative/mannequins/{$m->id}/reject", ['reason' => 'Işık çok sert, yüz detayları kayboluyor.'])
            ->assertRedirect();

        $m->refresh();
        $this->assertSame(Mannequin::REVIEW_REJECTED, $m->review_status);
        $this->assertSame('Işık çok sert, yüz detayları kayboluyor.', $m->review_note);
        Notification::assertSentTo($creator, ImageReviewDecisionNotification::class);
    }

    public function test_creator_cannot_approve_own_mannequin(): void
    {
        $creator = $this->creator();
        $creator->givePermissionTo('creative.approve');
        $m = $this->pendingMannequin($creator);

        $this->actingAs($creator)
            ->post("/creative/mannequins/{$m->id}/approve")
            ->assertForbidden();

        $this->assertSame(Mannequin::REVIEW_PENDING, $m->fresh()->review_status);
    }

    public function test_can_review_flag_hides_once_already_decided(): void
    {
        $creator        = $this->creator();
        $reviewer       = $this->reviewer();
        $otherReviewer  = $this->reviewer();
        $m              = $this->pendingMannequin($creator);

        $this->actingAs($reviewer)->post("/creative/mannequins/{$m->id}/approve")->assertRedirect();

        // Karar verildikten sonra, BAŞKA bir yönetici için bile onay/ret butonları görünmemeli.
        $this->actingAs($otherReviewer)
            ->get('/creative/mannequins')
            ->assertInertia(fn ($page) => $page
                ->component('Creative::CreativeMannequins')
                ->where('mannequins.0.can_review', false));
    }

    public function test_notify_pending_excludes_creator_and_reaches_other_approvers(): void
    {
        Notification::fake();
        $creator      = $this->creator();
        $otherAdmin   = $this->reviewer();
        $unrelated    = User::factory()->create(); // creative.approve yok
        $m            = $this->pendingMannequin($creator);
        $creator->givePermissionTo('creative.approve'); // üretici de admin olsa bile kendine gitmemeli

        app(ReviewNotifier::class)->notifyPending($m, $creator->id);

        Notification::assertSentTo($otherAdmin, ImagePendingReviewNotification::class);
        Notification::assertNotSentTo($creator, ImagePendingReviewNotification::class);
        Notification::assertNotSentTo($unrelated, ImagePendingReviewNotification::class);
    }

    // ── Ürün Giydirme ───────────────────────────────────────────────────

    private function pendingTryon(User $creator): TryonResult
    {
        $product = Product::factory()->create();
        $pose    = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);

        $staged = sprintf('products/%d/onmodel_staged_test.png', $product->id);
        Storage::disk('public')->put($staged, 'fake-bytes');

        return TryonResult::create([
            'product_id'        => $product->id,
            'pose_id'           => $pose->id,
            'status'            => TryonResult::STATUS_DONE,
            'staged_image_path' => $staged,
            'created_by'        => $creator->id,
            'review_status'     => TryonResult::REVIEW_PENDING,
        ]);
    }

    public function test_tryon_approve_publishes_to_product_images(): void
    {
        $creator  = $this->creator();
        $reviewer = $this->reviewer();
        $r        = $this->pendingTryon($creator);

        $this->assertDatabaseCount('product_images', 0);

        $this->actingAs($reviewer)
            ->post("/creative/tryon/{$r->id}/approve")
            ->assertRedirect();

        $r->refresh();
        $this->assertSame(TryonResult::REVIEW_APPROVED, $r->review_status);
        $this->assertNotNull($r->product_image_id);
        $this->assertSame($r->staged_image_path, ProductImage::find($r->product_image_id)->path);
    }

    public function test_tryon_reject_does_not_publish_to_product_images(): void
    {
        $creator  = $this->creator();
        $reviewer = $this->reviewer();
        $r        = $this->pendingTryon($creator);

        $this->actingAs($reviewer)
            ->post("/creative/tryon/{$r->id}/reject", ['reason' => 'Ürünün deseni net görünmüyor.'])
            ->assertRedirect();

        $r->refresh();
        $this->assertSame(TryonResult::REVIEW_REJECTED, $r->review_status);
        $this->assertNull($r->product_image_id);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_creator_can_review_own_tryon_result(): void
    {
        $creator = $this->creator();
        $creator->givePermissionTo('creative.approve');
        $r = $this->pendingTryon($creator);

        $this->actingAs($creator)
            ->post("/creative/tryon/{$r->id}/approve")
            ->assertRedirect();

        $r->refresh();
        $this->assertSame(TryonResult::REVIEW_APPROVED, $r->review_status);
        $this->assertSame($creator->id, $r->reviewed_by);
    }

    public function test_tryon_mannequin_picker_excludes_unapproved_identities(): void
    {
        $viewer = $this->creator();

        Mannequin::create(['name' => 'Onaylı', 'status' => Mannequin::STATUS_READY, 'reference_image_path' => 'a.png', 'review_status' => Mannequin::REVIEW_APPROVED]);
        Mannequin::create(['name' => 'Bekleyen', 'status' => Mannequin::STATUS_READY, 'reference_image_path' => 'b.png', 'review_status' => Mannequin::REVIEW_PENDING]);
        Mannequin::create(['name' => 'Reddedilen', 'status' => Mannequin::STATUS_READY, 'reference_image_path' => 'c.png', 'review_status' => Mannequin::REVIEW_REJECTED]);

        $this->actingAs($viewer)
            ->get('/creative/tryon')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Creative::CreativeTryon')
                ->has('mannequins', 1)
                ->where('mannequins.0.name', 'Onaylı'));
    }

    public function test_review_notifications_implement_should_broadcast(): void
    {
        $this->assertInstanceOf(ShouldBroadcast::class, new ImagePendingReviewNotification($this->pendingMannequin($this->creator())));

        $decision = new ImageReviewDecisionNotification($this->pendingMannequin($this->creator()), true);
        $this->assertInstanceOf(ShouldBroadcast::class, $decision);
    }
}
