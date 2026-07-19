<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\PostLoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $target = $request->session()->pull('url.intended', PostLoginRedirect::for($request->user()));

        // Tenant kullanıcıları kendi alt alan adlarına yönlendirilir. Bu,
        // login isteğini yapan origin'den farklı bir origin'e (subdomain)
        // gitmek demektir. Inertia form gönderimleri XHR ile yapıldığından
        // normal bir redirect() burada tarayıcının XHR'ı çapraz-origin'de
        // takip etmeye çalışmasına ve CORS başlığı olmadığı için sessizce
        // başarısız olmasına yol açar (login butonu yanıp söner, kullanıcı
        // login sayfasında kalır). Inertia::location() ise tam sayfa
        // navigasyonu tetikleyerek bunu doğru şekilde çözer.
        if (parse_url($target, PHP_URL_HOST) !== $request->getHost()) {
            return Inertia::location($target);
        }

        return redirect()->to($target);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
