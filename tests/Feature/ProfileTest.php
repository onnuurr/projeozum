<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        // User modeli SoftDeletes kullanıyor; Model::fresh() global scope'ları
        // (soft-delete scope dahil) bilerek atlar, bu yüzden silinmiş bir kaydı
        // yine de döner — assertNull() burada hiçbir zaman geçemez. Doğru kontrol
        // deleted_at'in DB'de set edildiğini doğrulamak.
        $this->assertSoftDeleted($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_phone_is_normalized_to_masked_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => '+90 532 123 45 67'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0 (532) 123 45 67', $user->refresh()->phone);
    }

    public function test_profile_phone_can_be_cleared_and_is_kept_when_omitted(): void
    {
        $user = User::factory()->create(['phone' => '0 (532) 123 45 67']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email])
            ->assertSessionHasNoErrors();
        $this->assertSame('0 (532) 123 45 67', $user->refresh()->phone);

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->refresh()->phone);
    }

    public function test_invalid_profile_phone_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => '12345'])
            ->assertSessionHasErrors('phone');
    }
}
