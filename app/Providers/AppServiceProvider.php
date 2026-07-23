<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogLockout;
use App\Listeners\LogPasswordReset;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Support\PostLoginRedirect;
use ArchitectureDoctor\Console\ArchitectureDoctorCommand;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ArchitectureDoctorCommand::class]);
        }

        Vite::prefetch(concurrency: 3);

        // ->toast('success', 'Kaydedildi.') kısayolu — Schema B'yi doğrudan oluşturur.
        RedirectResponse::macro('toast', function (string $type, string $message, ?string $title = null) {
            /** @var RedirectResponse $this */
            return $this->with('flash', ['toast' => ['type' => $type, 'title' => $title, 'message' => $message]]);
        });

        // Superadmin tüm yetenekleri otomatik geçer (eksik/yeni permission'larda kilitlenmeyi önler).
        // `null` döndürmek diğer kullanıcılar için normal yetki kontrolünü sürdürür.
        Gate::before(fn ($user, string $ability) => $user->hasRole('superadmin') ? true : null);

        // Login ekranı tek (subdomain'e göre ayrılmıyor); zaten giriş yapmış
        // kullanıcı /login'e giderse (guest middleware) de aynı tenant/merkez
        // ayrımına göre yönlendirilir — bkz. PostLoginRedirect.
        RedirectIfAuthenticated::redirectUsing(
            fn ($request) => PostLoginRedirect::for($request->user())
        );

        // ── Auth olay dinleyicileri (KVKK uyumlu aktivite loglama) ───────────
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        Event::listen(Lockout::class, LogLockout::class);
        Event::listen(PasswordReset::class, LogPasswordReset::class);
    }
}
