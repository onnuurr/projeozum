<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Creative\Jobs\GenerateMannequinJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\ReviewChat;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Reddedilen görseller için AI destekli düzeltme sohbetini doğrular: erişim
 * (üretici + yönetici, başkası değil), sadece REDDEDİLMİŞ kayıtlarda çalışması,
 * ve "talimatı uygula" aksiyonunun onay durumunu sıfırlayıp yeniden üretimi
 * kuyruğa alması (gerçek Gemini çağrısı yapılmadan).
 */
class ReviewChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.approve', 'guard_name' => 'web']);
    }

    private function creator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view');

        return $user;
    }

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.approve');

        return $user;
    }

    private function rejectedMannequin(User $creator): Mannequin
    {
        return Mannequin::create([
            'name' => 'M', 'status' => Mannequin::STATUS_READY, 'reference_image_path' => 'x.png',
            'created_by' => $creator->id, 'review_status' => Mannequin::REVIEW_REJECTED,
            'review_note' => 'Işık çok sert.',
        ]);
    }

    public function test_unrelated_user_cannot_open_chat(): void
    {
        $creator   = $this->creator();
        $unrelated = $this->creator();
        $m         = $this->rejectedMannequin($creator);

        $this->actingAs($unrelated)
            ->post("/creative/mannequins/{$m->id}/review-chat", ['message' => 'Merhaba'])
            ->assertForbidden();
    }

    public function test_chat_rejected_for_non_rejected_subject(): void
    {
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);
        $m->update(['review_status' => Mannequin::REVIEW_PENDING]);

        $this->actingAs($creator)
            ->post("/creative/mannequins/{$m->id}/review-chat", ['message' => 'Merhaba'])
            ->assertRedirect('/creative/mannequins')
            ->assertSessionHas('error');
    }

    public function test_creator_can_converse_and_reviewer_notification_is_parsed(): void
    {
        $creator  = $this->creator();
        $reviewer = $this->reviewer();
        $m        = $this->rejectedMannequin($creator);

        $this->mock(GeminiClient::class, function ($mock) {
            $mock->shouldReceive('generateText')
                ->once()
                ->andReturn("Işığı yumuşatmamız lazım.\nFINAL_INSTRUCTION: use soft diffused studio lighting");
        });

        $this->actingAs($creator)
            ->post("/creative/mannequins/{$m->id}/review-chat", ['message' => 'Işık neden bu kadar sert?'])
            ->assertRedirect();

        $this->assertSame(2, ReviewChat::where('subject_id', $m->id)->count());
        $this->assertSame('use soft diffused studio lighting', $m->fresh()->meta['chat_suggested_instruction']);

        // Yönetici de aynı sohbete katılabilir (üretici olmasa bile).
        $this->mock(GeminiClient::class, function ($mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('Tamamdır.');
        });
        $this->actingAs($reviewer)
            ->post("/creative/mannequins/{$m->id}/review-chat", ['message' => 'Ek bilgi var mı?'])
            ->assertRedirect();
    }

    public function test_apply_without_suggestion_returns_error(): void
    {
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);

        $this->actingAs($creator)
            ->post("/creative/mannequins/{$m->id}/review-chat/apply")
            ->assertRedirect();

        $this->assertSame(Mannequin::REVIEW_REJECTED, $m->fresh()->review_status);
    }

    public function test_apply_mannequin_appends_suggestion_and_requeues_job(): void
    {
        Queue::fake();
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);
        $m->update(['extras' => 'orijinal not', 'meta' => ['chat_suggested_instruction' => 'use soft diffused lighting']]);

        $this->actingAs($creator)
            ->post("/creative/mannequins/{$m->id}/review-chat/apply")
            ->assertRedirect();

        $m->refresh();
        $this->assertStringContainsString('orijinal not', $m->extras);
        $this->assertStringContainsString('use soft diffused lighting', $m->extras);
        $this->assertNull($m->review_status);
        Queue::assertPushed(GenerateMannequinJob::class, fn ($job) => $job->mannequinId === $m->id);
    }

    public function test_apply_tryon_sets_extra_instructions_and_requeues_job(): void
    {
        Queue::fake();
        $creator = $this->creator();
        $product = Product::factory()->create();
        $pose    = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing pose', 'status' => Pose::STATUS_READY]);
        $r       = TryonResult::create([
            'product_id' => $product->id, 'pose_id' => $pose->id,
            'status' => TryonResult::STATUS_DONE, 'created_by' => $creator->id,
            'review_status' => TryonResult::REVIEW_REJECTED, 'review_note' => 'Desen net değil.',
            'meta' => ['chat_suggested_instruction' => 'zoom in on the fabric pattern'],
        ]);

        $this->actingAs($creator)
            ->post("/creative/tryon/{$r->id}/review-chat/apply")
            ->assertRedirect();

        $r->refresh();
        $this->assertSame('zoom in on the fabric pattern', $r->meta['extra_instructions']);
        $this->assertNull($r->review_status);
        $this->assertSame(TryonResult::STATUS_QUEUED, $r->status);
        Queue::assertPushed(GenerateOnModelJob::class, fn ($job) => $job->resultId === $r->id);
    }

    public function test_show_page_requires_participant(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $creator   = $this->creator();
        $unrelated = $this->creator();
        $m         = $this->rejectedMannequin($creator);

        $this->actingAs($unrelated)
            ->get("/creative/mannequins/{$m->id}/review-chat")
            ->assertForbidden();
    }

    public function test_show_page_requires_rejected_status(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);
        $m->update(['review_status' => Mannequin::REVIEW_PENDING]);

        $this->actingAs($creator)
            ->get("/creative/mannequins/{$m->id}/review-chat")
            ->assertRedirect('/creative/mannequins')
            ->assertSessionHas('error');
    }

    public function test_show_page_renders_for_creator(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);

        $this->actingAs($creator)
            ->get("/creative/mannequins/{$m->id}/review-chat")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Creative::ReviewChat')
                ->where('subjectType', 'mannequin')
                ->where('subjectId', $m->id)
                ->where('reviewNote', 'Işık çok sert.'));
    }
}
