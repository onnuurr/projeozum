<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Finance\Contracts\EInvoiceProviderInterface;
use Modules\Finance\Models\OutgoingInvoice;
use Modules\Finance\Services\EInvoice\NullEInvoiceProvider;
use Modules\Finance\Services\EInvoice\TrendyolEFaturamProvider;
use Tests\TestCase;

class TrendyolEInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function config(): array
    {
        return [
            'driver'            => 'trendyol',
            'base_url'          => 'https://stage-apigateway.trendyolefaturam.com',
            'email'             => 'test@example.com',
            'password'          => 'secret',
            'company_id'        => 42,
            'source'            => 'PORTAL',
            'einvoice_type'     => 'EFATURA',
            'earchive_type'     => 'EARSIVFATURA',
            'invoice_type_code' => 'SATIS',
            'token_ttl'         => 3000,
        ];
    }

    private function invoiceWithItem(?string $taxNumber): OutgoingInvoice
    {
        $user = User::factory()->create();

        $invoice = OutgoingInvoice::create([
            'invoice_no'       => 'INV-TR-1',
            'buyer_name'       => 'ABC Ltd',
            'buyer_tax_number' => $taxNumber,
            'buyer_address'    => 'İstanbul',
            'issue_date'       => '2026-07-01',
            'subtotal'         => 500,
            'tax_amount'       => 100,
            'total'            => 600,
            'created_by'       => $user->id,
        ]);

        $invoice->items()->create([
            'item_name'      => 'Ürün A',
            'quantity'       => 2,
            'unit_price'     => 250,
            'vat_rate'       => 20,
            'taxable_amount' => 500,
            'vat_amount'     => 100,
            'line_total'     => 600,
        ]);

        return $invoice->fresh('items');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_registered_taxpayer_is_sent_as_einvoice_with_kurus_amounts(): void
    {
        Http::fake([
            '*/api/auth/signin' => Http::response([], 200, ['x-access-token' => 'tkn-1']),
            '*/api/invoice/taxpayers/*' => Http::response([['taxId' => '1234567890', 'aliasType' => 'INVOICE']], 200),
            '*/api/invoice/documents/outgoing-einvoice' => Http::response(['invoiceUuid' => 'uuid-ef', 'status' => 10], 200),
        ]);

        $result = (new TrendyolEFaturamProvider($this->config()))
            ->send($this->invoiceWithItem('1234567890'));

        $this->assertSame('uuid-ef', $result->uuid);
        $this->assertSame(OutgoingInvoice::EFATURA_PENDING, $result->status);
        $this->assertSame(TrendyolEFaturamProvider::DOC_EINVOICE, $result->rawResponse['document_type']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/invoice/documents/outgoing-einvoice')
                || str_contains($request->url(), '/status/')) {
                return false;
            }
            $line = $request['invoiceLines'][0];

            return $request->hasHeader('x-access-token', 'tkn-1')
                && $line['taxableAmount'] === 50000   // 500.00 TL -> kuruş
                && $line['taxAmount'] === 10000
                && $line['totalAmount'] === 60000
                && $request['invoiceInfo']['invoiceType'] === 'EFATURA';
        });
    }

    public function test_non_registered_taxpayer_falls_back_to_earchive(): void
    {
        Http::fake([
            '*/api/auth/signin' => Http::response([], 200, ['x-access-token' => 'tkn-2']),
            '*/api/invoice/taxpayers/*' => Http::response([], 404),
            '*/api/invoice/documents/earchive' => Http::response(['invoiceUuid' => 'uuid-ea', 'status' => 10], 200),
        ]);

        $result = (new TrendyolEFaturamProvider($this->config()))
            ->send($this->invoiceWithItem('9999999999'));

        $this->assertSame('uuid-ea', $result->uuid);
        $this->assertSame(TrendyolEFaturamProvider::DOC_EARCHIVE, $result->rawResponse['document_type']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/invoice/documents/earchive')
            && ! str_contains($request->url(), '/status/')
            && ! str_contains($request->url(), '/cancel')
            && $request['invoiceInfo']['invoiceType'] === 'EARSIVFATURA');
    }

    public function test_status_205_maps_to_success(): void
    {
        Http::fake([
            '*/api/auth/signin' => Http::response([], 200, ['x-access-token' => 'tkn-3']),
            '*/api/invoice/documents/earchive/status/*' => Http::response(['status' => 205, 'invoiceUuid' => 'uuid-ea'], 200),
        ]);

        $invoice = $this->invoiceWithItem('9999999999');
        $invoice->update([
            'efatura_uuid'         => 'uuid-ea',
            'efatura_raw_response' => ['document_type' => TrendyolEFaturamProvider::DOC_EARCHIVE],
        ]);

        $result = (new TrendyolEFaturamProvider($this->config()))->checkStatus($invoice->fresh());

        $this->assertSame(OutgoingInvoice::EFATURA_SUCCESS, $result->status);
    }

    public function test_cancel_earchive_posts_cancel_request(): void
    {
        Http::fake([
            '*/api/auth/signin' => Http::response([], 200, ['x-access-token' => 'tkn-4']),
            '*/api/invoice/documents/earchive/cancel' => Http::response([], 200),
        ]);

        $invoice = $this->invoiceWithItem('9999999999');
        $invoice->update([
            'efatura_uuid'         => 'uuid-ea',
            'efatura_raw_response' => ['document_type' => TrendyolEFaturamProvider::DOC_EARCHIVE],
        ]);

        $ok = (new TrendyolEFaturamProvider($this->config()))->cancel($invoice->fresh());

        $this->assertTrue($ok);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/invoice/documents/earchive/cancel')
            && $request['invoiceUuid'] === 'uuid-ea'
            && $request['companyId'] === 42);
    }

    public function test_einvoice_cannot_be_cancelled_via_api(): void
    {
        $invoice = $this->invoiceWithItem('1234567890');
        $invoice->update([
            'efatura_uuid'         => 'uuid-ef',
            'efatura_raw_response' => ['document_type' => TrendyolEFaturamProvider::DOC_EINVOICE],
        ]);

        $this->assertFalse((new TrendyolEFaturamProvider($this->config()))->cancel($invoice->fresh()));
    }

    public function test_binding_falls_back_to_null_without_credentials(): void
    {
        config(['finance.einvoice' => ['driver' => 'trendyol', 'email' => null, 'password' => null]]);
        $this->assertInstanceOf(NullEInvoiceProvider::class, $this->app->make(EInvoiceProviderInterface::class));

        config(['finance.einvoice' => array_merge($this->config())]);
        $this->assertInstanceOf(TrendyolEFaturamProvider::class, $this->app->make(EInvoiceProviderInterface::class));
    }
}
