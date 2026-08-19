<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Her modül kendi RouteServiceProvider'ında web.php'yi Route::middleware('web')
 * ile, api.php'yi Route::middleware('api') ile kaydediyor (bkz. her modülün
 * Providers/RouteServiceProvider.php dosyası) — 'web' grubu CSRF'i (ValidateCsrfToken) otomatik içerir,
 * 'api' grubu kasıtlı olarak stateless'tır (webhook'lar imza/token ile korunur, bkz.
 * WebhookRateLimitTest + TrendyolWebhookTest). Bu test o ayrımın HER modülde ve
 * gelecekte eklenecek her route'ta da doğru kaldığını garanti eder: 'api/' öneki
 * taşımayan (yani tarayıcı/Inertia'dan çağrılan) hiçbir route 'web' middleware
 * grubundan yoksun olmamalı — aksi halde o route sessizce CSRF korumasız kalır.
 */
class CsrfCoverageTest extends TestCase
{
    /**
     * Framework'ün kendi dahili route'ları — modül route dosyalarından gelmez,
     * CSRF kapsamı dışında tutulmaları beklenir (health check, storage sembolik linki).
     */
    private const EXEMPT_URIS = ['up', 'storage/{path}'];

    public function test_every_non_api_route_is_registered_under_the_web_middleware_group(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (str_starts_with($uri, 'api/') || str_starts_with($uri, 'sanctum/')) {
                continue;
            }
            if (in_array($uri, self::EXEMPT_URIS, true)) {
                continue;
            }

            if (! in_array('web', $route->gatherMiddleware(), true)) {
                $offenders[] = $uri . ' [' . implode(',', $route->methods()) . ']';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Şu route'lar tarayıcıdan erişilebilir görünüyor ama 'web' middleware grubunda "
            . "(dolayısıyla CSRF korumasında) değil:\n" . implode("\n", $offenders)
        );
    }
}
