<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bagisto\Mappers\TenantPayloadMapper;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAddress;
use Modules\Tenant\Services\TenantService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * İş ortağının fatura / sevk / iade adresleri: panel formu (SaveTenantRequest) → tenant_addresses,
 * ve Bagisto müşteri payload'ındaki `addresses` listesi.
 */
class TenantAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tenant.manage', 'guard_name' => 'web']);
        $role->givePermissionTo('tenant.manage');
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole($role);

        Queue::fake();
    }

    private function address(array $overrides = []): array
    {
        return array_merge([
            'contact_name' => 'Ayşe Yılmaz',
            'phone' => '0532 111 22 33',
            'address' => 'Bağdat Cad. No:142',
            'district' => 'Kadıköy',
            'city' => 'İstanbul',
            'postal_code' => '34728',
            'country' => 'TR',
        ], $overrides);
    }

    private function tenantPayload(array $addresses): array
    {
        return [
            'code' => 'ADR01',
            'name' => 'Adres Test',
            'owner_name' => 'Ayşe Yılmaz',
            'owner_email' => 'ayse@adres-test.com',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
            'addresses' => $addresses,
        ];
    }

    public function test_web_form_saves_three_separate_addresses(): void
    {
        $this->actingAs($this->superadmin)->post('/tenants', $this->tenantPayload([
            'billing' => $this->address(),
            'shipping' => $this->address(['address' => 'Depo Sok. No:3', 'district' => 'Tuzla']),
            'return' => $this->address(['address' => 'İade Sok. No:9', 'city' => 'Ankara', 'district' => 'Çankaya']),
        ]))->assertRedirect(route('tenants.index'))->assertSessionHasNoErrors();

        $tenant = Tenant::where('code', 'ADR01')->firstOrFail();

        $this->assertSame('Bağdat Cad. No:142', $tenant->addressOf(TenantAddress::TYPE_BILLING)->address);
        $this->assertSame('Tuzla', $tenant->addressOf(TenantAddress::TYPE_SHIPPING)->district);
        $this->assertSame('Ankara', $tenant->addressOf(TenantAddress::TYPE_RETURN)->city);
    }

    public function test_blank_address_removes_that_type_and_partial_address_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantService::class)->syncAddresses($tenant, array_fill_keys(TenantAddress::TYPES, $this->address()));

        app(TenantService::class)->syncAddresses($tenant, ['return' => ['address' => '  ']]);
        $this->assertEqualsCanonicalizing(['billing', 'shipping'], $tenant->addresses()->pluck('type')->all());

        $this->actingAs($this->superadmin)->post('/tenants', $this->tenantPayload([
            'billing' => ['address' => 'Bağdat Cad. No:142'],
        ]))->assertSessionHasErrors(['addresses.billing.city', 'addresses.billing.district']);
    }

    public function test_bagisto_payload_carries_typed_addresses_with_vat_only_on_billing(): void
    {
        $tenant = Tenant::factory()->create(['tax_number' => '1234567890', 'legal_name' => 'Adres Ltd. Şti.']);
        $tenant->users()->save(User::factory()->make(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@adres-test.com']));

        app(TenantService::class)->syncAddresses($tenant, [
            'billing' => $this->address(),
            'shipping' => $this->address(['contact_name' => 'Depo Sorumlusu', 'district' => 'Tuzla']),
            // İl/ilçesi eksik adres Bagisto'ya gönderilmez (müşteri adresi bunlarsız kaydedilemez).
            'return' => $this->address(['district' => '']),
        ]);

        $payload = app(TenantPayloadMapper::class)->toActivatedPayload($tenant->fresh(), null);

        $this->assertSame(['billing', 'shipping'], array_column($payload['addresses'], 'type'));
        $this->assertArrayNotHasKey('address', $payload);

        [$billing, $shipping] = $payload['addresses'];
        $this->assertSame('1234567890', $billing['vat_id']);
        $this->assertNull($shipping['vat_id']);
        $this->assertSame('Adres Ltd. Şti.', $shipping['company_name']);
        $this->assertSame(['Depo', 'Sorumlusu'], [$shipping['first_name'], $shipping['last_name']]);
        $this->assertSame(['İstanbul', 'Tuzla'], [$shipping['state'], $shipping['city']]); // İl → state, İlçe → city
    }
}
