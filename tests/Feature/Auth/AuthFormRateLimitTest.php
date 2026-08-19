<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * /login zaten kendi email+ip bazlı RateLimiter'ına sahip (bkz. LoginRateLimitTest).
 * /register ve /forgot-password, /reset-password ise route seviyesinde HİÇBİR
 * hız sınırına sahip değildi — bu, IP başına sınırsız hesap oluşturma/parola
 * sıfırlama maili tetikleme (mail-bombing, kaynak tüketimi) demekti. routes/auth.php'ye
 * mevcut verify-email deseniyle (throttle:6,1) tutarlı bir throttle eklendi; bu test
 * o korumayı doğrular.
 */
class AuthFormRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_rate_limited_after_six_attempts_per_minute(): void
    {
        // Şifre onayını kasıtlı yanlış bırakıyoruz ki başarılı kayıt olup
        // Auth::login() tetiklenmesin — aksi halde ikinci istekten itibaren
        // 'guest' middleware'i (kullanıcı artık authenticated olduğu için) throttle'a
        // ulaşmadan 302 döner ve sayaç hiç işlemez.
        for ($i = 0; $i < 6; $i++) {
            $this->post('/register', [
                'name'                  => "Kullanici {$i}",
                'email'                 => "user{$i}@example.com",
                'password'              => 'password',
                'password_confirmation' => 'uyusmuyor',
            ])->assertSessionHasErrors('password');
        }

        $response = $this->post('/register', [
            'name'                  => 'Yedinci',
            'email'                 => 'user7@example.com',
            'password'              => 'password',
            'password_confirmation' => 'uyusmuyor',
        ]);

        $response->assertStatus(429);
        $this->assertDatabaseMissing('users', ['email' => 'user7@example.com']);
    }

    public function test_forgot_password_is_rate_limited_after_six_attempts_per_minute(): void
    {
        Notification::fake();
        User::factory()->count(6)->create()->each(function (User $u, int $i) {
            $this->post('/forgot-password', ['email' => $u->email]);
        });

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertStatus(429);
    }

    public function test_reset_password_is_rate_limited_after_six_attempts_per_minute(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/reset-password', [
                'token'                 => 'gecersiz-token',
                'email'                 => "reset{$i}@example.com",
                'password'              => 'password',
                'password_confirmation' => 'password',
            ]);
        }

        $response = $this->post('/reset-password', [
            'token'                 => 'gecersiz-token',
            'email'                 => 'reset7@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(429);
    }
}
