<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

/**
 * User::$fillable içinde 'tenant_id' ve 'is_active' gibi hassas alanlar var (bkz.
 * app/Models/User.php). RegisteredUserController::store() bunları $request->all()
 * yerine elle seçilmiş bir dizi ile create() ettiği için mass assignment'a kapalı —
 * bu test o korumayı kilitler: kayıt formuna fazladan alan ekleyerek ayrıcalık
 * yükseltme (başka bir tenant'a bağlanma, hesabı önceden aktif/verified yapma,
 * superadmin rolü alma) denenir, hiçbirinin tutmaması beklenir.
 */
class RegistrationMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_extra_fields_in_registration_payload_are_ignored(): void
    {
        $foreignTenant = Tenant::factory()->create();

        $this->post('/register', [
            'name'                  => 'Saldırgan',
            'email'                 => 'attacker@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            // Mass assignment denemesi:
            'tenant_id'             => $foreignTenant->id,
            'is_active'             => false,
            'uuid'                  => 'sabitlenmis-uuid',
            'email_verified_at'     => now()->subYear()->toDateTimeString(),
            'two_factor_enabled'    => true,
            'id'                    => 999999,
        ]);

        $user = User::where('email', 'attacker@example.com')->firstOrFail();

        $this->assertNull($user->tenant_id);
        $this->assertNotSame($foreignTenant->id, $user->tenant_id);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertNotEquals('sabitlenmis-uuid', $user->uuid);
        $this->assertNotEquals(999999, $user->id);
        $this->assertFalse($user->hasRole('superadmin'));
        $this->assertFalse($user->hasRole('tenant'));
    }
}
