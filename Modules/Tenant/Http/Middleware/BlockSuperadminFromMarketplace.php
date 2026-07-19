<?php

namespace Modules\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * KVKK: pazaryeri bağlantı bilgileri (API key/secret) yalnızca tenant'ın kendi
 * kullanıcılarına özeldir. ResolveTenantFromSubdomain superadmin'in portal
 * subdomain'ine genel erişimine izin verir (destek amaçlı); bu middleware o
 * bypass'ı özellikle /marketplace grubu için iptal eder.
 */
class BlockSuperadminFromMarketplace
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperadmin()) {
            abort(403, 'Pazaryeri bağlantı bilgileri superadmin tarafından görüntülenemez/yönetilemez.');
        }

        return $next($request);
    }
}
