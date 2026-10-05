<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CSRF cookie'si "XSRF-TOKEN" yerine uygulamaya özgü adla yazılır (üst domaindeki diğer
        // uygulamaların XSRF-TOKEN'ıyla çakışıp 419 veriyordu) — bkz. App\Http\Middleware\ValidateCsrfToken.
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\ValidateCsrfToken::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SetTenantContext::class,
        ]);

        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'log.access'         => \App\Http\Middleware\EnsureLogAccess::class,
            'tenant.owns'        => \Modules\Tenant\Http\Middleware\EnforceTenantOwnership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Doğrulama / auth / 404 hatalarını ayrı DB kaydı oluşturmadan sadece default kanala bırak
        $exceptions->dontReport([
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ]);

        // Kalan tüm Throwable'ları ErrorLog'a ve errors kanalına yaz
        $exceptions->report(function (\Throwable $e): void {
            app(\App\Logging\ErrorLogger::class)->capture($e);
        });

        // 419 (CSRF/oturum süresi doldu): Inertia isteklerinde çıplak "Page Expired" sayfası yerine
        // geri yönlendir (303: PUT/DELETE'in aynı metotla tekrarlanmaması için) + uyarı toast'ı. Oturum tamamen düştüyse auth middleware login'e atar.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() === 419 && $request->header('X-Inertia')) {
                return back(303)->with('warning', __('auth.session_expired'));
            }

            // 403 (yetki yok — spatie permission/role middleware, policy, abort(403)): JSON istemeyen
            // isteklerde çıplak Laravel hata sayfası yerine Errors/Forbidden (birkaç sn sonra ana sayfaya yönlendirir).
            if ($response->getStatusCode() === 403 && ! $request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/Forbidden')
                    ->toResponse($request)
                    ->setStatusCode(403);
            }

            return $response;
        });
    })->create();
