<?php

namespace Tests\Feature\Logging;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auth olay dinleyicilerinin KVKK uyumlu aktivite loglamasını doğrular.
 * sqlite :memory: kullanır, RefreshDatabase ile her test temiz başlar.
 */
class AuthActivityLogTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Başarılı giriş
    // -------------------------------------------------------------------------

    public function test_successful_login_writes_activity_log(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('activity_logs', [
            'action'    => 'auth.login',
            'module'    => 'auth',
            'causer_id' => $user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Başarısız giriş — KVKK kabul testi
    // -------------------------------------------------------------------------

    public function test_failed_login_writes_activity_log_and_masks_email_never_logs_password(): void
    {
        event(new Failed('web', null, [
            'email'    => 'ahmet@gmail.com',
            'password' => 'sup3rsecret',
        ]));

        // Kayıt yazıldı mı?
        $this->assertDatabaseHas('activity_logs', [
            'action'  => 'auth.login_failed',
            'module'  => 'auth',
            'level'   => 'warning',
        ]);

        $row = ActivityLog::where('action', 'auth.login_failed')->firstOrFail();

        // JSON sütununda ham parola kesinlikle bulunmamalı (KVKK)
        $rowJson = json_encode($row->toArray());
        $this->assertStringNotContainsString('sup3rsecret', $rowJson,
            'KVKK: parola hiçbir kolonda saklanmamalı');

        // E-posta da ham hâliyle bulunmamalı (maskelenmiş olmalı)
        $this->assertStringNotContainsString('ahmet@gmail.com', $rowJson,
            'KVKK: e-posta maskelenmeden saklanmamalı');

        // properties içinde e-posta maskeli olarak bulunmalı
        $props = $row->properties ?? [];
        $this->assertArrayHasKey('email', $props,
            'properties içinde email anahtarı bulunmalı (maskeli olarak)');
        $this->assertStringContainsString('@', $props['email'],
            'Maskelenen e-posta @ içermeli');
        $this->assertStringNotContainsString('ahmet@gmail.com', $props['email'],
            'E-posta ham hâliyle properties\'ta olmamalı');

        // properties içinde parola anahtarı olmamalı
        $this->assertArrayNotHasKey('password', $props,
            'properties içinde password anahtarı olmamalı');
    }

    // -------------------------------------------------------------------------
    // Başarılı çıkış
    // -------------------------------------------------------------------------

    public function test_successful_logout_writes_activity_log(): void
    {
        $user = User::factory()->create();

        event(new Logout('web', $user));

        $this->assertDatabaseHas('activity_logs', [
            'action'    => 'auth.logout',
            'module'    => 'auth',
            'causer_id' => $user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Parola sıfırlama
    // -------------------------------------------------------------------------

    public function test_password_reset_writes_activity_log(): void
    {
        $user = User::factory()->create();

        event(new PasswordReset($user));

        $this->assertDatabaseHas('activity_logs', [
            'action'    => 'auth.password_reset',
            'module'    => 'auth',
            'causer_id' => $user->id,
        ]);
    }
}
