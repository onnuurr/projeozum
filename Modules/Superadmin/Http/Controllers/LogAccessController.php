<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Models\Setting;

class LogAccessController extends Controller
{
    public function showUnlock(): Response
    {
        $hash = Setting::getGroup('security')['logAccessPasswordHash'] ?? null;

        return Inertia::render('Superadmin::LogAccessUnlock', [
            'passwordSet' => filled($hash),
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $hash = Setting::getGroup('security')['logAccessPasswordHash'] ?? null;

        if (! filled($hash)) {
            return redirect()->route('superadmin.settings')
                ->with('warning', 'Önce Ayarlar\'dan log erişim şifresi belirleyin.');
        }

        if (Hash::check($request->password, $hash)) {
            $request->session()->put('superadmin.log_access_confirmed_at', now()->timestamp);

            // KVKK: şifreyi loglamıyoruz — sadece başarı kaydı
            app(\App\Logging\ActivityLogger::class)->log(
                'auth.log_access',
                'Log erişim kapısı açıldı',
                [
                    'module' => 'superadmin',
                    'level'  => 'info',
                ]
            );

            // Route'u doğrudan URL ile belirtiyoruz; görüntüleyici route'u sonraki görevde eklenecek.
            return redirect('/superadmin/logs');
        }

        // KVKK: girilen şifreyi loglamıyoruz — sadece başarısızlık kaydı
        app(\App\Logging\ActivityLogger::class)->log(
            'auth.log_access',
            'Log erişim kapısı açılamadı — hatalı şifre',
            [
                'module' => 'superadmin',
                'level'  => 'warning',
            ]
        );

        return back()->withErrors(['password' => 'Şifre hatalı.']);
    }
}
