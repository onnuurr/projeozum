<?php

namespace Modules\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenant\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * API route'larında {tenant} param'ı auth user'ın tenant_id'si ile eşleşmeli (superadmin hariç).
 *
 * Kullanım: ->middleware(['auth:sanctum', 'can:tenant.manage', 'tenant.owns'])
 * Route param ismi sabit: {tenant} (Eloquent model binding).
 */
class EnforceTenantOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isSuperadmin()) {
            return $next($request);
        }

        $tenantParam = $request->route('tenant');
        $tenantId = $tenantParam instanceof Tenant ? $tenantParam->id : (int) $tenantParam;
        if (! $tenantId) {
            abort(404, 'Tenant param yok.');
        }
        if ((int) $user->tenant_id !== $tenantId) {
            abort(403, 'Bu tenant\'a erişim yetkiniz yok.');
        }

        return $next($request);
    }
}
