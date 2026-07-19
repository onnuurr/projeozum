<?php

namespace App\Support;

use App\Models\User;
use Modules\Tenant\Models\Tenant;

/**
 * Login ekranı tek — hangi host'tan (merkezi domain veya bir tenant
 * subdomain'i) yapıldığına değil, giriş yapan kullanıcının kendi tipine göre
 * yönlendirme kararı verir: tenant kullanıcısı kendi bayi portalına,
 * superadmin/staff merkezi panele gider.
 */
class PostLoginRedirect
{
    public static function for(?User $user): string
    {
        if ($user && $user->tenant_id && ! $user->isSuperadmin()) {
            $tenant = Tenant::query()->find($user->tenant_id);
            $portalDomain = config('app.portal_domain');

            if ($tenant && $portalDomain) {
                return "https://{$tenant->slug}.{$portalDomain}/";
            }
        }

        // `route('dashboard')` KULLANMA: 'dashboard' domain'e bağlı olmayan bir
        // route olduğundan, URL generator o anki isteğin host'unu kullanır —
        // bu closure bir tenant subdomain'inden tetiklenirse (ör. zaten
        // superadmin olarak giriş yapılmış oturumla bir tenant subdomain'ine
        // gidilmesi) "abc-2.ozumserver.com.tr/dashboard" gibi yanlış bir URL
        // üretir. APP_URL'i sabit kullanarak her zaman merkezi domain'e gider.
        return rtrim(config('app.url'), '/').'/dashboard';
    }
}
