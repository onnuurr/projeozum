<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Laravel'in ValidateCsrfToken::runningUnitTests() metodu test ortamında (console +
 * testing env) her zaman true döner ve CSRF kontrolünü tamamen atlar (bkz.
 * vendor/laravel/framework/.../ValidateCsrfToken::handle()). Bu yüzden normal bir
 * Feature testinde '$this->post(...)' çağrısı CSRF token göndermeden de her zaman
 * başarılı olur — bu, korumanın çalıştığını KANITLAMAZ, sadece test client'ın
 * bypass'ından kaynaklanır. Bu test, middleware'i o bypass kapatılmış halde
 * (gerçek prod davranışını simüle ederek) doğrudan çağırıp asıl korumayı doğrular.
 */
class CsrfProtectionTest extends TestCase
{
    private function makeRequest(string $method, ?string $sessionToken, ?string $submittedToken): Request
    {
        $request = Request::create(
            '/login',
            $method,
            $submittedToken !== null ? ['_token' => $submittedToken] : []
        );

        $session = new Store('test-session', new NullSessionHandler());
        $session->start();
        if ($sessionToken !== null) {
            $session->put('_token', $sessionToken);
        }
        $request->setLaravelSession($session);

        return $request;
    }

    private function middleware(): ValidateCsrfToken
    {
        return new class($this->app, $this->app->make('encrypter')) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }

    public function test_post_without_token_is_rejected(): void
    {
        $request = $this->makeRequest('POST', 'gercek-token', null);

        $this->expectException(TokenMismatchException::class);

        $this->middleware()->handle($request, fn ($req) => response('ok'));
    }

    public function test_post_with_wrong_token_is_rejected(): void
    {
        $request = $this->makeRequest('POST', 'gercek-token', 'yanlis-token');

        $this->expectException(TokenMismatchException::class);

        $this->middleware()->handle($request, fn ($req) => response('ok'));
    }

    public function test_post_with_matching_session_token_passes(): void
    {
        $request = $this->makeRequest('POST', 'gercek-token', 'gercek-token');

        $response = $this->middleware()->handle($request, fn ($req) => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_get_requests_are_exempt_from_csrf(): void
    {
        $request = $this->makeRequest('GET', 'gercek-token', null);

        $response = $this->middleware()->handle($request, fn ($req) => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }
}
