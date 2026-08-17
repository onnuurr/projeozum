<?php

namespace Tests\Unit\Bagisto;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Bagisto\Services\BagistoSyncClient;
use Tests\TestCase;

/**
 * Faz 1: `X-Saas-Signature` imzasının, Bagisto tarafındaki
 * `Webkul\SaasSync\Http\Middleware\VerifySaasWebhookSignature::handle()`
 * ile aynı algoritmayla (HMAC-SHA256, ham gövde) üretildiğini doğrular.
 */
class BagistoSyncClientTest extends TestCase
{
    public function test_post_signs_body_with_configured_secret(): void
    {
        config([
            'bagisto.sync.base_url'       => 'https://bagisto.test',
            'bagisto.sync.webhook_secret' => 'shared-secret',
        ]);

        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        (new BagistoSyncClient)->post('api/saas-sync/products', ['sku' => 'PRD-1', 'event' => 'created']);

        Http::assertSent(function ($request) {
            $expected = hash_hmac('sha256', $request->body(), 'shared-secret');

            return $request->url() === 'https://bagisto.test/api/saas-sync/products'
                && $request->hasHeader('X-Saas-Signature', $expected)
                && $request->method() === 'POST';
        });
    }

    public function test_post_throws_on_failed_response(): void
    {
        config([
            'bagisto.sync.base_url'       => 'https://bagisto.test',
            'bagisto.sync.webhook_secret' => 'shared-secret',
        ]);

        Http::fake(['*' => Http::response(['message' => 'invalid signature'], 401)]);

        $this->expectException(RequestException::class);

        (new BagistoSyncClient)->post('api/saas-sync/products', ['sku' => 'PRD-1']);
    }

    public function test_post_is_skipped_when_not_configured(): void
    {
        config([
            'bagisto.sync.base_url'       => '',
            'bagisto.sync.webhook_secret' => '',
        ]);

        Http::fake();

        (new BagistoSyncClient)->post('api/saas-sync/products', ['sku' => 'PRD-1']);

        Http::assertNothingSent();
    }
}
