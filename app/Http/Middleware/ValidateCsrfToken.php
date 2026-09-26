<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as BaseValidateCsrfToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * CSRF cookie'sini varsayılan "XSRF-TOKEN" yerine uygulamaya özgü bir adla yazar.
 *
 * Session domain'i ".ozumserver.com.tr" (üst domain) ve aynı domain altında başka Laravel
 * uygulamaları (bg., saas., site. …) da kendi "XSRF-TOKEN" cookie'lerini üst domaine yazabiliyor.
 * Tarayıcıda aynı adla iki cookie olunca axios ilk eşleşeni (çoğu zaman yabancı/bayat olanı)
 * X-XSRF-TOKEN header'ına koyuyor → web POST'larında aralıklı 419. Kendi adımızla bu çakışma
 * ortadan kalkar.
 *
 * Frontend eşi: resources/js/bootstrap.js → axios.defaults.xsrfCookieName (aynı değer olmalı).
 * Doğrulama tarafı değişmez: header yine X-XSRF-TOKEN, çözümleme CookieValuePrefix::remove()
 * ile ada bağlı olmadan yapılır.
 */
class ValidateCsrfToken extends BaseValidateCsrfToken
{
    public const COOKIE_NAME = 'OZUM-XSRF-TOKEN';

    protected function newCookie($request, $config)
    {
        $cookie = parent::newCookie($request, $config);

        return new Cookie(
            self::COOKIE_NAME,
            $cookie->getValue(),
            $cookie->getExpiresTime(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            false,
            false,
            $cookie->getSameSite(),
            $cookie->isPartitioned(),
        );
    }
}
