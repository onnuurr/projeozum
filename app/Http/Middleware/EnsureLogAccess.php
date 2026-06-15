<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogAccess
{
    /** Oturum geçerlilik süresi (saniye) */
    private const TIMEOUT = 1800; // 30 dakika

    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('superadmin.log_access_confirmed_at');

        if ($confirmedAt && (time() - $confirmedAt) < self::TIMEOUT) {
            return $next($request);
        }

        return redirect()->route('superadmin.logs.unlock');
    }
}
