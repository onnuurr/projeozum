<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Modules\Bagisto\Jobs\PushStorefrontFeaturesToBagisto;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Bagisto\Services\StorefrontFeatureService;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * İş Ortakları › Bayi Portalı: Bagisto mağazası özellik anahtarları (SaaS tek doğruluk kaynağı).
 */
class StorefrontSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['bagisto.sync.base_url' => 'https://store.test', 'bagisto.sync.webhook_secret' => 'secret']);

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'bagisto.manage', 'guard_name' => 'web']);
        $role->givePermissionTo('bagisto.manage');
        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
    }

    public function test_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/bagisto/settings')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/bagisto/settings', ['features' => ['bulk_order_excel' => true]])->assertForbidden();
    }

    public function test_page_lists_features_with_excel_bulk_order_off_by_default(): void
    {
        $this->actingAs($this->staff)
            ->get('/bagisto/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Bagisto::StorefrontSettings', false) // modül sayfası: Inertia test bulucusu Modül::Sayfa çözmüyor (bkz. SupplierInvoiceTest)
                ->where('features.0.key', 'bulk_order_excel')
                ->where('features.0.enabled', false)
                ->where('configured', true)
                ->where('sync.state', null));
    }

    public function test_saving_stores_known_features_and_pushes_to_store(): void
    {
        $this->actingAs($this->staff)
            ->put('/bagisto/settings', ['features' => ['bulk_order_excel' => true, 'cari_account' => false, 'bilinmeyen' => true]])
            ->assertRedirect();

        $service = app(StorefrontFeatureService::class);

        $this->assertTrue($service->values()['bulk_order_excel']);
        $this->assertFalse($service->values()['cari_account']);
        $this->assertArrayNotHasKey('bilinmeyen', $service->values());
        $this->assertSame('pending', $service->syncStatus()['state']);
        Queue::assertPushed(PushStorefrontFeaturesToBagisto::class);
    }

    public function test_job_sends_all_features_signed_and_marks_synced(): void
    {
        Http::fake(['store.test/*' => Http::response(['data' => []])]);

        app(StorefrontFeatureService::class)->update(['bulk_order_excel' => true]);

        (new PushStorefrontFeaturesToBagisto)->handle(app(StorefrontFeatureService::class), app(BagistoSyncClient::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://store.test/api/saas-sync/features'
            && $request['features'] === ['bulk_order_excel' => true, 'cari_account' => false, 'hide_prices_for_guests' => true, 'dealer_applications' => true]
            && $request->hasHeader('X-Saas-Signature'));

        $status = app(StorefrontFeatureService::class)->syncStatus();
        $this->assertSame('synced', $status['state']);
        $this->assertNotNull($status['synced_at']);
    }

    public function test_failed_push_is_shown_on_the_page(): void
    {
        (new PushStorefrontFeaturesToBagisto)->failed(new RuntimeException('HTTP request returned status code 401'));

        $this->actingAs($this->staff)
            ->get('/bagisto/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sync.state', 'failed')
                ->where('sync.error', 'HTTP request returned status code 401'));
    }

    public function test_manual_sync_queues_a_push(): void
    {
        $this->actingAs($this->staff)->post('/bagisto/settings/sync')->assertRedirect();

        Queue::assertPushed(PushStorefrontFeaturesToBagisto::class);
    }
}
