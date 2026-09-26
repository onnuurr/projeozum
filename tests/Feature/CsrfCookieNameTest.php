<?php

namespace Tests\Feature;

use App\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Üst domaindeki diğer uygulamaların "XSRF-TOKEN" cookie'siyle çakışmamak için CSRF cookie'si
 * uygulamaya özgü adla yazılır (bkz. ValidateCsrfToken). resources/js/bootstrap.js aynı adı okur.
 */
class CsrfCookieNameTest extends TestCase
{
    public function test_csrf_cookie_uses_app_specific_name(): void
    {
        $response = $this->get('/login');

        $response->assertCookie(ValidateCsrfToken::COOKIE_NAME);
        $response->assertCookieMissing('XSRF-TOKEN');
    }

    public function test_frontend_reads_the_same_cookie_name(): void
    {
        $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));

        $this->assertStringContainsString("xsrfCookieName = '".ValidateCsrfToken::COOKIE_NAME."'", $bootstrap);
    }
}
