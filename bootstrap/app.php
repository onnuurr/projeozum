<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
    })->create();
