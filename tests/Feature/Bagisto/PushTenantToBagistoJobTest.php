<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bagisto\Jobs\PushTenantToBagisto;
use Modules\Bagisto\Mappers\TenantPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Carrier;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PushTenantToBagistoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bagisto.sync.base_url' => 'https://bagisto.test',
            'bagisto.sync.webhook_secret' => 'shared-secret',
        ]);

        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
    }

    public function test_handle_posts_activated_payload_with_owner_identity(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $tenant = Tenant::factory()->create(['email' => 'business-contact@example.com', 'phone' => '5551112233']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ali Veli', 'email' => 'ali@example.com']);
        $owner->assignRole('tenant');

        (new PushTenantToBagisto($tenant->id, 'activated', 'secret123'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://bagisto.test/api/saas-sync/tenants'
                && $request['event'] === 'activated'
                // Owner e-postası kullanılmalı, Tenant.email (iş iletişim alanı) değil.
                && $request['email'] === 'ali@example.com'
                && $request['first_name'] === 'Ali'
                && $request['last_name'] === 'Veli'
                && $request['password'] === 'secret123';
        });
    }

    public function test_handle_posts_discount_rate(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $tenant = Tenant::factory()->create(['discount_rate' => 12.5]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ali@example.com']);
        $owner->assignRole('tenant');

        (new PushTenantToBagisto($tenant->id, 'activated'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        // Bagisto bayiyi bu orana ait müşteri grubuna alır (SaasSync DealerDiscountGroups).
        Http::assertSent(fn ($request) => $request['discount_rate'] === 12.5);
    }

    public function test_handle_posts_shipping_agreement_fields_when_set(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $carrier = Carrier::create(['code' => 'aras', 'name' => 'Aras Kargo', 'is_active' => true]);

        $tenant = Tenant::factory()->create([
            'email' => 'business-contact@example.com',
            'shipping_agreement_type' => 'own',
            'carrier_id' => $carrier->id,
        ]);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ali Veli', 'email' => 'ali@example.com']);
        $owner->assignRole('tenant');

        (new PushTenantToBagisto($tenant->id, 'activated'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertSent(function ($request) {
            return $request['shipping_agreement_type'] === 'own'
                && $request['shipping_terms_accepted'] === false
                // carrier_name kargo FİRMASININ adıdır, takip numarası değil.
                && $request['carrier_name'] === 'Aras Kargo';
        });
    }

    public function test_handle_omits_shipping_agreement_fields_when_not_set(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $tenant = Tenant::factory()->create(['email' => 'business-contact@example.com']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ali Veli', 'email' => 'ali@example.com']);
        $owner->assignRole('tenant');

        (new PushTenantToBagisto($tenant->id, 'activated'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertSent(function ($request) {
            return ! isset($request['shipping_agreement_type']);
        });
    }

    public function test_handle_posts_deactivated_payload_without_password(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ali@example.com']);
        $owner->assignRole('tenant');

        (new PushTenantToBagisto($tenant->id, 'deactivated'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertSent(function ($request) {
            return $request['event'] === 'deactivated'
                && $request['email'] === 'ali@example.com'
                && ! isset($request['password']);
        });
    }

    public function test_handle_skips_silently_when_tenant_missing(): void
    {
        Http::fake();

        (new PushTenantToBagisto(999999, 'activated'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertNothingSent();
    }

    public function test_handle_skips_silently_when_tenant_has_no_owner(): void
    {
        Http::fake();

        $tenant = Tenant::factory()->create();

        (new PushTenantToBagisto($tenant->id, 'activated'))
            ->handle(app(TenantPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertNothingSent();
    }

    public function test_plain_password_is_not_stored_in_queue_payload(): void
    {
        // Gerçek serileştirme için fake değil database sürücüsü: payload jobs tablosuna yazılır
        // (başarısız olursa aynı payload failed_jobs'a kopyalanır).
        config(['queue.default' => 'database']);

        PushTenantToBagisto::dispatch(1, 'activated', 'GizliSifre-123');

        $payload = (string) \Illuminate\Support\Facades\DB::table('jobs')->value('payload');
        $this->assertNotSame('', $payload);
        $this->assertStringNotContainsString('GizliSifre-123', $payload);
    }
}
