<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HandleInertiaRequests::share() 'auth.user' prop'unu (dolayısıyla User::name'i) her
 * sayfada paylaşır; bu prop ilk yüklemede app.blade.php'deki @inertia direktifi ile
 * <div id="app" data-page="...">'ye HTML-attribute olarak gömülür. Kullanıcı adına
 * '</script><script>alert(1)</script>' gibi bir stored payload girilirse ve bu
 * attribute doğru escape edilmiyorsa, sayfayı açan HERKESİN tarayıcısında çalışan
 * bir stored XSS oluşur (ör. admin panelinde kullanıcı listesini gören bir yönetici).
 * Bu test, ismi payload'a çevirip tam sayfa yüklemesinin ham HTML'inde payload'ın
 * yalnızca escape'lenmiş biçimde bulunduğunu, çalıştırılabilir/açık bir <script>
 * etiketi olarak bulunmadığını doğrular.
 */
class ProfileNameXssTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_payload_in_profile_name_is_escaped_on_every_page_render(): void
    {
        $user = User::factory()->create(['name' => 'Kurban']);
        $payload = '</script><script>alert(document.cookie)</script>';

        $this->actingAs($user)
            ->patch('/profile', [
                'name'  => $payload,
                'email' => $user->email,
            ])
            ->assertRedirect();

        $this->assertSame($payload, $user->fresh()->name);

        $html = $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->getContent();

        $this->assertStringNotContainsString(
            '<script>alert(document.cookie)</script>',
            $html,
            'Kullanıcı adı sayfaya escape edilmeden gömülmüş — stored XSS.'
        );
        $this->assertStringNotContainsString('</script><script>', $html);
    }
}
