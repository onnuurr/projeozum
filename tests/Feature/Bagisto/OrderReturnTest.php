<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Bagisto\Jobs\NotifyTenantOfReturn;
use Modules\Bagisto\Jobs\PushReturnStateToBagisto;
use Modules\Finance\Models\OutgoingInvoice;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderReturn;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bayi iade akışı: Bagisto RMA webhook'u → SaaS kararı → (iade faturası) → para iadesi.
 * Bkz. Modules\Bagisto\Services\BagistoReturnService.
 */
class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bagisto.inbound.token' => 'test-token']);
        Queue::fake();
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        $this->tenant = Tenant::factory()->create(['current_balance' => 0]);
        $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'magaza@example.com']);
        $owner->assignRole('tenant');

        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80]);
    }

    private function webhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer test-token')->postJson('/api/webhooks/bagisto', $payload);
    }

    private function order(string $paymentMethod = 'cari_hesap', bool $chargeCredit = true): Order
    {
        $this->webhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 900,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 2, 'price' => 120]],
                'payment_method' => $paymentMethod,
                'charge_credit' => $chargeCredit,
            ],
        ])->assertStatus(201);

        return Order::where('bagisto_order_id', '900')->firstOrFail();
    }

    private function rmaSnapshot(array $overrides = []): array
    {
        return array_replace([
            'rma_id' => 41,
            'order_id' => 900,
            'customer_email' => 'magaza@example.com',
            'status_id' => 1,
            'status_title' => 'Pending Review',
            'reason' => 'Dikiş açılması',
            'items' => [['sku' => 'VAR-1', 'qty' => 1]],
            'images' => ['https://bg.example.com/storage/rma/1.jpg'],
        ], $overrides);
    }

    private function returnFromWebhook(array $overrides = []): OrderReturn
    {
        $this->webhook(['event' => 'return.created', 'data' => $this->rmaSnapshot($overrides)])->assertOk();

        return OrderReturn::where('bagisto_rma_id', (string) ($overrides['rma_id'] ?? 41))->firstOrFail();
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function allPermissions(): User
    {
        return $this->staff(['order-return.view', 'order-return.manage', 'order-return.refund']);
    }

    public function test_return_webhook_records_request_at_wholesale_price(): void
    {
        $order = $this->order();

        $return = $this->returnFromWebhook();

        $this->assertSame(OrderReturn::STATUS_REQUESTED, $return->status);
        $this->assertSame($order->id, $return->order_id);
        $this->assertSame($this->tenant->id, $return->tenant_id);
        // Bayinin ödediği toptan birim fiyat (80), Bagisto'nun gönderdiği perakende fiyat değil.
        $this->assertEquals(80.0, (float) $return->requested_amount);
        $this->assertSame(['https://bg.example.com/storage/rma/1.jpg'], $return->images);
    }

    public function test_repeated_snapshots_are_idempotent_and_do_not_override_saas_decision(): void
    {
        $this->order();
        $return = $this->returnFromWebhook();

        $this->actingAs($this->allPermissions())
            ->post("/order-returns/{$return->id}/reject", ['note' => 'Kullanım hatası'])
            ->assertSessionHasNoErrors();

        $this->webhook(['event' => 'return.inspection_saved', 'data' => $this->rmaSnapshot([
            'inspection' => ['result' => 'approved', 'refund_amount' => 80],
        ])])->assertOk();

        $this->assertSame(1, OrderReturn::count());
        $fresh = $return->fresh();
        $this->assertSame(OrderReturn::STATUS_REJECTED, $fresh->status);
        $this->assertSame('approved', $fresh->inspection['result']);
    }

    public function test_bagisto_cancellation_closes_open_return(): void
    {
        $this->order();
        $this->returnFromWebhook();

        $this->webhook(['event' => 'return.status_updated', 'data' => $this->rmaSnapshot(['status_id' => 9])])->assertOk();

        $this->assertSame(OrderReturn::STATUS_CANCELLED, OrderReturn::first()->status);
    }

    public function test_approve_is_capped_by_order_total(): void
    {
        $this->order();
        $return = $this->returnFromWebhook();

        $this->actingAs($this->allPermissions())
            ->post("/order-returns/{$return->id}/approve", ['amount' => 500])
            ->assertSessionHasErrors('return');

        $this->assertSame(OrderReturn::STATUS_REQUESTED, $return->fresh()->status);
    }

    public function test_cari_refund_credits_the_dealer_account(): void
    {
        $this->order();
        $this->assertEquals(160.0, (float) $this->tenant->fresh()->current_balance);
        $return = $this->returnFromWebhook();
        $user = $this->allPermissions();

        $this->actingAs($user)->post("/order-returns/{$return->id}/approve", ['amount' => 80])->assertSessionHasNoErrors();
        $this->assertFalse($return->fresh()->invoice_required);

        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasNoErrors();

        $fresh = $return->fresh();
        $this->assertSame(OrderReturn::STATUS_REFUNDED, $fresh->status);
        $this->assertSame(OrderReturn::REFUND_CARI, $fresh->refund_method);
        $this->assertEquals(80.0, (float) $this->tenant->fresh()->current_balance);

        Queue::assertPushed(PushReturnStateToBagisto::class);
        Queue::assertPushed(NotifyTenantOfReturn::class, 2);
    }

    public function test_invoiced_order_needs_accepted_return_invoice_before_refund(): void
    {
        $order = $this->order();
        OutgoingInvoice::create([
            'invoice_no' => 'OZM2026000001',
            'invoice_type' => OutgoingInvoice::TYPE_SALES_ORDER,
            'order_id' => $order->id,
            'buyer_name' => 'Mağaza',
            'issue_date' => now()->toDateString(),
            'total' => 160,
            'status' => OutgoingInvoice::STATUS_SENT,
            'created_by' => User::factory()->create()->id,
        ]);
        $return = $this->returnFromWebhook();
        $user = $this->allPermissions();

        $this->actingAs($user)->post("/order-returns/{$return->id}/approve", ['amount' => 80])->assertSessionHasNoErrors();
        $this->assertTrue($return->fresh()->invoice_required);
        $this->assertSame(OrderReturn::INVOICE_AWAITING, $return->fresh()->invoice_status);

        // Fatura yok → ödeme yapılamaz.
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasErrors('return');
        $this->assertSame(OrderReturn::STATUS_APPROVED, $return->fresh()->status);

        // Bayi portaldan faturayı yükler (başka bayinin e-postasıyla 404).
        $upload = fn (string $email) => $this->withHeader('Authorization', 'Bearer test-token')->post('/api/bagisto/returns/41/invoice', [
            'email' => $email,
            'invoice_no' => 'IAD2026000001',
            'invoice_date' => now()->toDateString(),
            'invoice_amount' => 80,
            'file' => UploadedFile::fake()->create('iade.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        User::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'email' => 'baska@example.com']);
        $upload('baska@example.com')->assertNotFound();
        $upload('magaza@example.com')->assertOk()->assertJsonPath('data.invoice_status', OrderReturn::INVOICE_PENDING);

        // Kontrol edilmeden hâlâ ödeme yok.
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasErrors('return');

        $this->actingAs($user)->post("/order-returns/{$return->id}/invoice-review", ['accept' => true])->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasNoErrors();

        $this->assertSame(OrderReturn::STATUS_REFUNDED, $return->fresh()->status);
        Storage::disk('local')->assertExists($return->fresh()->invoice_path);
    }

    public function test_card_paid_order_is_refunded_through_bagisto_iyzico(): void
    {
        config(['bagisto.sync.base_url' => 'https://bg.example.com', 'bagisto.sync.webhook_secret' => 'secret']);
        Http::fake(['bg.example.com/api/saas-sync/refunds' => Http::response(['data' => ['refund_id' => 'PAY-777']])]);

        $this->order('iyzico', false);
        $return = $this->returnFromWebhook();
        $user = $this->allPermissions();

        $this->actingAs($user)->post("/order-returns/{$return->id}/approve", ['amount' => 80])->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasNoErrors();

        $fresh = $return->fresh();
        $this->assertSame(OrderReturn::REFUND_CARD, $fresh->refund_method);
        $this->assertSame('iyzico:PAY-777', $fresh->refund_reference);
        Http::assertSent(fn ($request) => $request->url() === 'https://bg.example.com/api/saas-sync/refunds'
            && $request['amount'] == 80 && $request['bagisto_order_id'] === '900'
            && $request->hasHeader('X-Saas-Signature'));
    }

    public function test_failed_card_refund_keeps_return_approved(): void
    {
        config(['bagisto.sync.base_url' => 'https://bg.example.com', 'bagisto.sync.webhook_secret' => 'secret']);
        Http::fake(['bg.example.com/*' => Http::response(['message' => 'iyzico: yetersiz bakiye'], 422)]);

        $this->order('iyzico', false);
        $return = $this->returnFromWebhook();
        $user = $this->allPermissions();

        $this->actingAs($user)->post("/order-returns/{$return->id}/approve", ['amount' => 80]);
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasErrors('return');

        $this->assertSame(OrderReturn::STATUS_APPROVED, $return->fresh()->status);
    }

    public function test_bank_refund_requires_transfer_reference(): void
    {
        $this->order('moneytransfer', false);
        $return = $this->returnFromWebhook();
        $user = $this->allPermissions();

        $this->actingAs($user)->post("/order-returns/{$return->id}/approve", ['amount' => 80]);
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund")->assertSessionHasErrors('return');
        $this->actingAs($user)->post("/order-returns/{$return->id}/refund", ['reference' => 'EFT-123'])->assertSessionHasNoErrors();

        $this->assertSame(OrderReturn::REFUND_BANK, $return->fresh()->refund_method);
        $this->assertSame('EFT-123', $return->fresh()->refund_reference);
    }

    public function test_refund_needs_its_own_permission(): void
    {
        $this->order();
        $return = $this->returnFromWebhook();

        $this->actingAs($this->staff(['order-return.view', 'order-return.manage']))
            ->post("/order-returns/{$return->id}/approve", ['amount' => 80])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff(['order-return.view', 'order-return.manage']))
            ->post("/order-returns/{$return->id}/refund")
            ->assertForbidden();

        $this->withoutVite();
        $viewer = $this->staff(['order-return.view']);

        $this->actingAs($viewer)->get('/order-returns')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Bagisto::OrderReturns', false)->has('returns.data', 1));
        $this->actingAs($viewer)->get("/order-returns/{$return->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Bagisto::OrderReturnDetail', false)->where('refundMethod', 'cari'));
    }

    public function test_dealer_is_notified_by_mail_and_sms(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->tenant->update(['contact_phone' => '905551112233']);
        $sms = \Mockery::mock(\App\Contracts\SmsProviderInterface::class);
        $sms->shouldReceive('send')->once()
            ->withArgs(fn ($phone, $text) => $phone === '905551112233' && str_contains($text, 'iade faturanizi'))
            ->andReturn(new \App\DTO\SmsSendResult(success: true));
        $this->app->instance(\App\Contracts\SmsProviderInterface::class, $sms);

        $order = $this->order();
        $return = $this->returnFromWebhook();
        $return->update(['status' => OrderReturn::STATUS_APPROVED, 'approved_amount' => 80, 'invoice_required' => true, 'invoice_status' => OrderReturn::INVOICE_AWAITING]);

        (new NotifyTenantOfReturn($return->id, NotifyTenantOfReturn::EVENT_APPROVED))->handle($sms);

        \Illuminate\Support\Facades\Mail::assertSent(\Modules\Bagisto\Mail\ReturnStatusMail::class, fn ($mail) => $mail->hasTo('magaza@example.com'));

        $html = (new \Modules\Bagisto\Mail\ReturnStatusMail($return->fresh(), NotifyTenantOfReturn::EVENT_APPROVED))->render();
        $this->assertStringContainsString('iade faturanızı', $html);
        $this->assertStringContainsString($order->order_no, $html);
    }
}
