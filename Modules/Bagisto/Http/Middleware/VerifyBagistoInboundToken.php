<?php

namespace Modules\Bagisto\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bagisto'nun `PushEventToSaas` job'ı bu isteği `Http::withToken()` ile, yani
 * `Authorization: Bearer <SAAS_SYNC_API_KEY>` header'ıyla atar (HMAC değil).
 */
class VerifyBagistoInboundToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('bagisto.inbound.token');

        if ($expected === '') {
            return response()->json(['message' => 'Bagisto inbound token yapılandırılmamış.'], 500);
        }

        if (! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Geçersiz veya eksik token.'], 401);
        }

        return $next($request);
    }
}
