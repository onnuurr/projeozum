<?php

namespace Tests\Unit\Sms;

use App\Contracts\SmsProviderInterface;
use App\Services\Sms\IletiMerkeziSmsProvider;
use App\Services\Sms\NullSmsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IletiMerkeziSmsProviderTest extends TestCase
{
    private function config(): array
    {
        return [
            'base_url' => 'https://api.iletimerkezi.com/v1',
            'key'      => 'test-key',
            'hash'     => 'test-hash',
            'sender'   => 'OZUMSERVER',
        ];
    }

    public function test_successful_send_normalizes_local_phone_and_parses_order_id(): void
    {
        Http::fake([
            '*/send-sms/json' => Http::response([
                'response' => [
                    'status' => ['code' => 200, 'message' => 'İşlem başarılı'],
                    'order'  => ['id' => '312891245'],
                ],
            ], 200),
        ]);

        $result = (new IletiMerkeziSmsProvider($this->config()))->send('05551234567', 'Doğrulama kodunuz: 123456');

        $this->assertTrue($result->success);
        $this->assertSame('312891245', $result->orderId);
        $this->assertNull($result->errorCode);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/send-sms/json')
                && $request['request']['authentication']['key'] === 'test-key'
                && $request['request']['authentication']['hash'] === 'test-hash'
                && $request['request']['order']['sender'] === 'OZUMSERVER'
                && $request['request']['order']['iys'] === '0'
                && $request['request']['order']['message']['receipents']['number'] === ['905551234567'];
        });
    }

    public function test_accepts_plus90_and_bare_905_formats(): void
    {
        Http::fake(['*/send-sms/json' => Http::response(['response' => ['status' => ['code' => 200], 'order' => ['id' => '1']]], 200)]);

        $provider = new IletiMerkeziSmsProvider($this->config());

        $this->assertTrue($provider->send('+905551234567', 'x')->success);
        $this->assertTrue($provider->send('905551234567', 'x')->success);
    }

    public function test_invalid_phone_never_calls_api(): void
    {
        Http::fake();

        $result = (new IletiMerkeziSmsProvider($this->config()))->send('123', 'x');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Geçersiz telefon', $result->errorMessage);
        Http::assertNothingSent();
    }

    public function test_api_error_code_is_surfaced_as_failure(): void
    {
        Http::fake([
            '*/send-sms/json' => Http::response([
                'response' => ['status' => ['code' => 452, 'message' => 'Alıcı listesi hatalı.']],
            ], 200),
        ]);

        $result = (new IletiMerkeziSmsProvider($this->config()))->send('05551234567', 'x');

        $this->assertFalse($result->success);
        $this->assertSame(452, $result->errorCode);
        $this->assertSame('Alıcı listesi hatalı.', $result->errorMessage);
    }

    public function test_null_provider_never_calls_http_and_reports_failure(): void
    {
        Http::fake();

        $result = (new NullSmsProvider())->send('05551234567', 'x');

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    public function test_binding_falls_back_to_null_without_credentials(): void
    {
        config(['services.sms' => ['driver' => 'iletimerkezi', 'iletimerkezi' => ['key' => null, 'hash' => null, 'sender' => null]]]);
        $this->assertInstanceOf(NullSmsProvider::class, $this->app->make(SmsProviderInterface::class));

        config(['services.sms' => ['driver' => 'iletimerkezi', 'iletimerkezi' => $this->config()]]);
        $this->assertInstanceOf(IletiMerkeziSmsProvider::class, $this->app->make(SmsProviderInterface::class));
    }
}
