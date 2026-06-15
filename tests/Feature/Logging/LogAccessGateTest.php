<?php

namespace Tests\Feature\Logging;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Superadmin\Models\Setting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Superadmin log erişim kapısının (separate-password gate) bütünleşik testleri.
 *
 * sqlite :memory: kullanır; RefreshDatabase ile her test temiz başlar.
 * KVKK: girilen şifrenin hiçbir activity_logs satırında yer almadığı doğrulanır.
 */
class LogAccessGateTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role            = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole($role);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Unlock sayfasına GET yapılabilmeli (şifre belirlenmemiş hâl)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unlock_page_is_reachable_for_superadmin(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('superadmin.logs.unlock'));

        $response->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Şifre belirlenmemişken POST → ayarlar sayfasına warning ile redirect
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unlock_without_password_configured_redirects_to_settings(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.logs.unlock.post'), ['password' => 'herhangi']);

        $response->assertRedirect(route('superadmin.settings'));
        $response->assertSessionHas('warning');

        // Oturum kilit bayrağı set edilmemiş olmalı
        $this->assertNull(session('superadmin.log_access_confirmed_at'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Doğru şifre → oturum bayrağı set edilir ve logs'a yönlendirilir
    // ─────────────────────────────────────────────────────────────────────────

    public function test_correct_password_sets_session_and_redirects(): void
    {
        $plainPassword = 'G3Cici!Log$2026';
        Setting::setGroup('security', [
            'logAccessPasswordHash' => Hash::make($plainPassword),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.logs.unlock.post'), ['password' => $plainPassword]);

        // Oturum bayrağı set edilmiş olmalı
        $response->assertSessionHas('superadmin.log_access_confirmed_at');

        // superadmin.logs rotasına yönlendirme (URL yeterli; rota adı henüz mevcut değil)
        $response->assertRedirect('/superadmin/logs');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Yanlış şifre → hata döner, oturum bayrağı set edilmez
    // ─────────────────────────────────────────────────────────────────────────

    public function test_wrong_password_returns_error_and_does_not_set_session(): void
    {
        Setting::setGroup('security', [
            'logAccessPasswordHash' => Hash::make('dogru-sifre'),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.logs.unlock.post'), ['password' => 'yanlis-sifre']);

        $response->assertSessionHasErrors(['password']);
        $this->assertNull(session('superadmin.log_access_confirmed_at'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. EnsureLogAccess middleware — onaysız oturum yönlendirme
    // ─────────────────────────────────────────────────────────────────────────

    public function test_middleware_redirects_when_session_not_confirmed(): void
    {
        // Middleware'i doğrudan test etmek için web grubunu içeren geçici route kaydedelim
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:superadmin', 'log.access'])
            ->get('/_test/log-access-probe', fn () => response('ok'))
            ->name('_test.log.probe');

        // Onaysız oturum → unlock'a yönlendirmeli
        $response = $this->actingAs($this->superadmin)
            ->get('/_test/log-access-probe');

        $response->assertRedirect(route('superadmin.logs.unlock'));
    }

    public function test_middleware_allows_access_when_session_confirmed(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:superadmin', 'log.access'])
            ->get('/_test/log-access-probe2', fn () => response('ok'))
            ->name('_test.log.probe2');

        // Oturum bayrağı set et (taze — şu an zamanı)
        $response = $this->actingAs($this->superadmin)
            ->withSession(['superadmin.log_access_confirmed_at' => now()->timestamp])
            ->get('/_test/log-access-probe2');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. KVKK: girilen şifre activity_logs satırlarında asla bulunmamalı
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kvkk_password_never_logged_on_success(): void
    {
        $plainPassword = 'KVKK-Test$2026!';
        Setting::setGroup('security', [
            'logAccessPasswordHash' => Hash::make($plainPassword),
        ]);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.logs.unlock.post'), ['password' => $plainPassword]);

        $logs = ActivityLog::where('action', 'auth.log_access')->get();
        $this->assertNotEmpty($logs, 'Başarılı unlock için activity log yazılmış olmalı');

        foreach ($logs as $log) {
            $rowJson = json_encode($log->toArray());
            $this->assertStringNotContainsString(
                $plainPassword,
                $rowJson,
                'KVKK: girilen şifre activity_logs satırında bulunmamalı (başarılı unlock)'
            );
        }
    }

    public function test_kvkk_password_never_logged_on_failure(): void
    {
        $wrongPassword = 'KVKK-Yanlis$2026!';
        Setting::setGroup('security', [
            'logAccessPasswordHash' => Hash::make('dogru-sifre'),
        ]);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.logs.unlock.post'), ['password' => $wrongPassword]);

        $logs = ActivityLog::where('action', 'auth.log_access')->get();
        $this->assertNotEmpty($logs, 'Başarısız unlock için activity log yazılmış olmalı');

        foreach ($logs as $log) {
            $rowJson = json_encode($log->toArray());
            $this->assertStringNotContainsString(
                $wrongPassword,
                $rowJson,
                'KVKK: girilen şifre activity_logs satırında bulunmamalı (başarısız unlock)'
            );
        }
    }
}
