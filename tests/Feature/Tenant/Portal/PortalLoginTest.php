<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

/**
 * Login ekranı tek (subdomain'e göre ayrı route yok) — hangi host'tan giriş
 * yapıldığına bakılmaksızın, kullanıcı tipine göre doğru yere yönlenmeli.
 */
class PortalLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_login_from_tenant_subdomain_redirects_to_own_portal(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->post('http://' . $tenant->slug . '.bizimsite.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('https://' . $tenant->slug . '.bizimsite.test/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_tenant_user_login_from_central_domain_redirects_to_own_portal(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->post('http://bizimsite.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('https://' . $tenant->slug . '.bizimsite.test/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_user_login_redirects_to_central_dashboard_regardless_of_host(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $staff = User::factory()->create(['tenant_id' => null]);

        $this->post('http://' . $tenant->slug . '.bizimsite.test/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect(rtrim(config('app.url'), '/') . '/dashboard');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_wrong_password_on_tenant_subdomain_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->post('http://' . $tenant->slug . '.bizimsite.test/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_already_authenticated_tenant_user_visiting_login_is_sent_to_own_portal(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        // Paylaşılan oturum yüzünden merkezi domain'den veya başka bir
        // subdomain'den /login'e gelinse bile kendi portalına gitmeli.
        $this->get('http://bizimsite.test/login')
            ->assertRedirect('https://' . $tenant->slug . '.bizimsite.test/');
    }

    public function test_already_authenticated_staff_visiting_login_is_sent_to_dashboard(): void
    {
        $staff = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($staff);

        $this->get('http://bizimsite.test/login')
            ->assertRedirect(rtrim(config('app.url'), '/') . '/dashboard');
    }

    public function test_staff_login_from_tenant_subdomain_redirects_to_central_dashboard_not_subdomain(): void
    {
        // Regresyon: route('dashboard') isteğin o anki host'unu kullanır —
        // bir tenant subdomain'inden tetiklenirse yanlışlıkla
        // "{slug}.domain/dashboard" üretebiliyordu. Her zaman APP_URL'e gitmeli.
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $staff = User::factory()->create(['tenant_id' => null]);

        $expected = rtrim(config('app.url'), '/') . '/dashboard';

        $this->post('http://' . $tenant->slug . '.bizimsite.test/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect($expected);
    }

    public function test_already_authenticated_staff_visiting_tenant_subdomain_login_redirects_to_central_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid()]);
        $staff = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($staff);

        $expected = rtrim(config('app.url'), '/') . '/dashboard';

        $this->get('http://' . $tenant->slug . '.bizimsite.test/login')
            ->assertRedirect($expected);
    }
}
