<?php
// tests/Feature/Tenant/Portal/InactiveUserTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password'  => 'sifre1234',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'sifre1234',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
