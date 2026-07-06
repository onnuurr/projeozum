<?php

namespace Modules\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenant\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subdomain routing: {slug}.{portal_domain} → Tenant resolve.
 *
 * Akış:
 *  1) Slug yoksa veya tenant bulunmazsa 404.
 *  2) Tenant pasifse 403.
 *  3) Auth user'ın tenant_id'si farklıysa 403 (superadmin bypass).
 *  4) Başarılı: app('current_tenant_id') set edilir + request'e attach.
 *
 * Route group içinde {slug} param'ı zorunlu: Route::domain('{slug}.'.config('app.portal_domain'))
 */
class ResolveTenantFromSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        if (! $slug) {
            abort(404, 'Tenant slug bulunamadı.');
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->where('slug', $slug)->first();
        if (! $tenant) {
            abort(404, 'Tenant bulunamadı.');
        }
        if (! $tenant->is_active) {
            abort(403, 'Bu tenant aktif değil.');
        }

        $user = $request->user();
        if ($user && ! $user->isSuperadmin()) {
            if ((int) $user->tenant_id !== (int) $tenant->id) {
                abort(403, 'Bu tenant\'a erişim yetkiniz yok.');
            }
        }

        app()->instance('current_tenant_id', $tenant->id);
        app()->instance('current_tenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        // {slug} route parametresi controller signature'ında yer almadığı için
        // burada unutulur — aksi halde array_values pozisyonlarını kaydırıp
        // "Argument #2 must be of type Order, string given" tarzı TypeError'a yol açar.
        $request->route()->forgetParameter('slug');

        return $next($request);
    }
}
