<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LoginRequest::ensureIsNotRateLimited() 5 başarısız denemeden sonra
 * (email+ip anahtarlı) kilitler — bkz. app/Http/Requests/Auth/LoginRequest.php.
 * Bu test brute-force korumasının gerçekten devreye girdiğini doğrular.
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_failed_attempt_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email'    => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password', // doğru şifre bile olsa kilitliyken geçmemeli
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_successful_login_clears_the_rate_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email'    => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticated();
        $this->post('/logout');

        // Başarılı girişten sonra sayaç sıfırlanmalı — hemen ardından tekrar
        // başarısız denemeler kilide takılmadan normal 'invalid credentials'
        // hatası dönmeli (rate limit hatası değil).
        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('saniye', (string) session('errors')->first('email'));
    }

    public function test_different_ip_is_not_affected_by_another_ips_lockout(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
    }
}
